<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateMjTasksTable extends Migrator
{
    public function change()
    {
        if ($this->hasTable('mj_tasks')) {
            return;
        }

        $table = $this->table('mj_tasks');
        $table
            ->addColumn('task_id', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 32])
            ->addColumn('prompt', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 64, 'default' => 'queued'])
            ->addColumn('progress', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('image_url', 'text', ['null' => true])
            ->addColumn('fail_reason', 'text', ['null' => true])
            ->addColumn('meta', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['task_id'], ['unique' => false])
            ->create();
    }
}
