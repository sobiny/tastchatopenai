<?php
namespace app\api\controller;

use app\common\model\MjTask;
use app\common\service\MidjourneyClient;
use app\common\validate\MjTaskValidate;
use think\Controller;
use think\Request;
use think\exception\HttpResponseException;

class Midjourney extends Controller
{
    protected $client;

    protected function initialize()
    {
        parent::initialize();
        $config = config('midjourney');
        $this->client = new MidjourneyClient($config);
    }

    public function create(Request $request)
    {
        $data = $this->requestData($request);
        $validate = new MjTaskValidate();
        if (!$validate->check($data)) {
            return $this->error($validate->getError());
        }

        if ($data['action'] !== 'IMAGINE') {
            throw new HttpResponseException($this->error('不支持的操作类型'));
        }

        $payload = [
            'prompt' => $data['prompt'],
            'action' => 'IMAGINE',
            'notifyHook' => $this->resolveCallbackUrl($data['notify_hook'] ?? null),
        ];

        if (!empty($data['task_id'])) {
            $payload['taskId'] = $data['task_id'];
        }
        if (!empty($data['index'])) {
            $payload['index'] = (int) $data['index'];
        }
        $options = $this->extractArray($data, 'options');
        if (!empty($options)) {
            $payload['options'] = $options;
        }

        $imageUrls = $this->normalizeImageUrls($data);
        if (!empty($imageUrls)) {
            $payload['imageUrlList'] = $imageUrls;
            if (count($imageUrls) === 1) {
                $payload['imageUrl'] = $imageUrls[0];
            }
        } elseif (!empty($data['image_url'])) {
            $payload['imageUrl'] = $data['image_url'];
        }

        $response = $this->client->imagine($payload);

        $task = new MjTask();
        $task->save([
            'task_id' => $this->extractTaskId($response),
            'action' => $data['action'],
            'prompt' => $data['prompt'],
            'status' => 'queued',
            'progress' => '0%',
            'meta' => $payload,
        ]);

        return $this->success('任务已创建', $task->toArray());
    }

    public function upscale(Request $request)
    {
        $data = $this->requestData($request);
        $payload = $this->buildActionPayload('UPSCALE', $data);
        $response = $this->client->upscale($payload);
        return $this->persistActionTask('UPSCALE', $payload, $response, $data['prompt'] ?? '');
    }

    public function variation(Request $request)
    {
        $data = $this->requestData($request);
        $payload = $this->buildActionPayload('VARIATION', $data);
        $response = $this->client->variation($payload);
        return $this->persistActionTask('VARIATION', $payload, $response, $data['prompt'] ?? '');
    }

    public function describe(Request $request)
    {
        $data = $this->requestData($request);
        $payload = [
            'imageUrl' => $data['prompt'] ?? '',
            'notifyHook' => $this->resolveCallbackUrl($data['notify_hook'] ?? null),
        ];
        $response = $this->client->describe($payload);
        return $this->persistActionTask('DESCRIBE', $payload, $response, $data['prompt'] ?? '');
    }

    public function list()
    {
        $tasks = MjTask::order('created_at', 'desc')->limit(100)->select();
        return $this->success('ok', collection($tasks)->toArray());
    }

    public function detail($id)
    {
        $task = MjTask::get($id);
        if (!$task) {
            return $this->error('任务不存在', 404);
        }
        if ($task->task_id) {
            $latest = $this->client->fetchTask($task->task_id);
            if (!empty($latest)) {
                $this->applyStatus($task, $latest);
                $task->save();
            }
        }
        return $this->success('ok', $task->toArray());
    }

    public function callback(Request $request)
    {
        $payload = $this->requestData($request);
        if (empty($payload['taskId'])) {
            return $this->error('缺少 taskId', 400);
        }

        $task = MjTask::where('task_id', $payload['taskId'])->find();
        if (!$task) {
            $task = new MjTask();
            $task->task_id = $payload['taskId'];
        }

        $this->applyStatus($task, $payload);
        $task->save();

        return $this->success('状态已更新', $task->toArray());
    }

    public function uploadImage(Request $request)
    {
        $file = $request->file('image');
        if (!$file) {
            return $this->error('未检测到上传图片');
        }

        $savePath = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($savePath) && !mkdir($savePath, 0755, true) && !is_dir($savePath)) {
            return $this->error('上传目录创建失败');
        }

        $info = $file->validate([
            'size' => 10 * 1024 * 1024,
            'ext' => 'jpg,jpeg,png,gif,webp,avif',
        ])->move($savePath);

        if (!$info) {
            return $this->error($file->getError() ?: '图片上传失败');
        }

        $relativePath = '/uploads/' . str_replace('\\', '/', $info->getSaveName());
        $url = rtrim($request->domain(), '/') . $relativePath;

        return $this->success('上传成功', [
            'url' => $url,
            'path' => $relativePath,
            'name' => $info->getFilename(),
        ]);
    }

    protected function buildActionPayload($action, array $data)
    {
        if (empty($data['task_id'])) {
            throw new HttpResponseException($this->error('task_id 必填'));
        }
        if (empty($data['index'])) {
            throw new HttpResponseException($this->error('index 必填'));
        }
        $index = (int) $data['index'];
        if ($index < 1 || $index > 4) {
            throw new HttpResponseException($this->error('index 必须在 1 到 4 之间'));
        }
        $payload = [
            'taskId' => $data['task_id'],
            'index' => $index,
            'notifyHook' => $this->resolveCallbackUrl($data['notify_hook'] ?? null),
            'prompt' => $data['prompt'] ?? '',
            'action' => $action,
        ];

        $options = $this->extractArray($data, 'options');
        if (!empty($options)) {
            $payload['options'] = $options;
        }

        return $payload;
    }

    protected function persistActionTask($action, array $payload, array $response, $prompt)
    {
        $task = new MjTask();
        $task->save([
            'task_id' => $this->extractTaskId($response),
            'action' => $action,
            'prompt' => $prompt,
            'status' => 'queued',
            'progress' => '0%',
            'meta' => $payload,
        ]);
        return $this->success('任务已创建', $task->toArray());
    }

    protected function applyStatus(MjTask $task, array $payload)
    {
        $task->status = $payload['status'] ?? $task->status;
        $task->progress = $payload['progress'] ?? $task->progress;
        $task->image_url = $payload['imageUrl'] ?? $payload['imageUrlList'][0] ?? $task->image_url;
        $task->fail_reason = $payload['failReason'] ?? $payload['failReasonMsg'] ?? $task->fail_reason;
        $task->meta = array_merge($task->meta ?? [], $payload);
    }

    protected function extractTaskId(array $response)
    {
        return $response['result'] ?? $response['taskId'] ?? $response['id'] ?? null;
    }

    protected function resolveCallbackUrl($custom)
    {
        if (!empty($custom)) {
            return $custom;
        }

        $configured = config('midjourney.callback_url');
        if (empty($configured)) {
            throw new HttpResponseException($this->error('未配置回调地址'));
        }

        return $configured;
    }

    protected function requestData(Request $request)
    {
        $data = $request->post();
        if (!empty($data)) {
            return $data;
        }
        $raw = $request->getContent();
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpResponseException($this->error('请求体不是合法 JSON'));
        }
        return $decoded;
    }

    protected function extractArray(array $data, $key)
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            return [];
        }
        if (is_array($data[$key])) {
            return $data[$key];
        }
        if (is_string($data[$key])) {
            $decoded = json_decode($data[$key], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    protected function normalizeImageUrls(array $data)
    {
        $urls = $this->extractArray($data, 'image_urls');
        if (!empty($urls)) {
            return array_values(array_filter(array_unique(array_map('trim', $urls))));
        }
        if (!empty($data['image_url']) && is_string($data['image_url'])) {
            return [trim($data['image_url'])];
        }
        return [];
    }

    protected function success($message, $data = null, $code = 200)
    {
        return json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message, $code = 400)
    {
        return json([
            'message' => $message,
        ], $code);
    }
}
