<?php
namespace app\common\service;

use Exception;
use think\Log;

class MidjourneyClient
{
    protected $baseUrl;
    protected $token;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim(array_get($config, 'base_url', ''), '/');
        $this->token = array_get($config, 'token');
        if (empty($this->baseUrl) || empty($this->token)) {
            throw new Exception('Midjourney API configuration is incomplete.');
        }
    }

    public function imagine(array $payload)
    {
        return $this->post('/mj/task', $payload);
    }

    public function upscale(array $payload)
    {
        return $this->post('/mj/task/upscale', $payload);
    }

    public function variation(array $payload)
    {
        return $this->post('/mj/task/variation', $payload);
    }

    public function describe(array $payload)
    {
        return $this->post('/mj/task/describe', $payload);
    }

    public function fetchTask($taskId)
    {
        return $this->get('/mj/task/' . $taskId);
    }

    protected function headers()
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
        ];
    }

    protected function post($path, array $data)
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('Midjourney API request failed: ' . $error);
            throw new Exception('Midjourney API request failed: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($response, true);
        if ($status >= 400) {
            Log::error('Midjourney API error', ['status' => $status, 'response' => $decoded]);
            throw new Exception('Midjourney API error: ' . ($decoded['message'] ?? $response));
        }
        return $decoded;
    }

    protected function get($path)
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('Midjourney API request failed: ' . $error);
            throw new Exception('Midjourney API request failed: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($response, true);
        if ($status >= 400) {
            Log::error('Midjourney API error', ['status' => $status, 'response' => $decoded]);
            throw new Exception('Midjourney API error: ' . ($decoded['message'] ?? $response));
        }
        return $decoded;
    }
}
