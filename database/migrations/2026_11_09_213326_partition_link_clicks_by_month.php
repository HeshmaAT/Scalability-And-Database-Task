<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $partition = DB::selectFromWriteConnection("
            SELECT PARTITION_METHOD AS method
            FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'link_clicks'
            LIMIT 1
        ")[0] ?? null;

        if ($partition !== null && $partition->method !== null) {
            return;
        }

        DB::statement('
            ALTER TABLE link_clicks
            DROP FOREIGN KEY IF EXISTS link_clicks_link_id_foreign
        ');

        DB::statement("
            ALTER TABLE link_clicks
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (id, clicked_at)
            PARTITION BY RANGE COLUMNS (clicked_at) (
                PARTITION p_history VALUES LESS THAN ('2026-08-01'),
                PARTITION p202608 VALUES LESS THAN ('2026-09-01'),
                PARTITION p202609 VALUES LESS THAN ('2026-10-01'),
                PARTITION p202610 VALUES LESS THAN ('2026-11-01'),
                PARTITION p202611 VALUES LESS THAN ('2026-12-01'),
                PARTITION p202612 VALUES LESS THAN ('2027-01-01'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE link_clicks REMOVE PARTITIONING');

        DB::statement('
            ALTER TABLE link_clicks
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (id)
        ');

        DB::statement('
            ALTER TABLE link_clicks
            ADD CONSTRAINT link_clicks_link_id_foreign
                FOREIGN KEY (link_id) REFERENCES links (id)
        ');
    }
};
