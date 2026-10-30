<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shop used to run on UTC and now runs on Malaysia time (UTC+8). Times already saved
 * were written in UTC, so they move forward eight hours to keep showing the real moment.
 */
return new class extends Migration
{
    /**
     * Dates picked in a calendar already mean a Malaysian day, so they stay as they are.
     *
     * @var array<string, list<string>>
     */
    private const LOCAL_DATE_COLUMNS = ['vouchers' => ['expires_at']];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->shiftStoredTimes(8);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->shiftStoredTimes(-8);
    }

    private function shiftStoredTimes(int $hours): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (Schema::getTables(DB::getDatabaseName()) as $table) {
            $columns = collect(Schema::getColumns($table['name']))
                ->filter(fn (array $column) => in_array($column['type_name'], ['timestamp', 'datetime'], true))
                ->pluck('name')
                ->reject(fn (string $column) => in_array($column, self::LOCAL_DATE_COLUMNS[$table['name']] ?? [], true));

            foreach ($columns as $column) {
                DB::table($table['name'])
                    ->whereNotNull($column)
                    ->update([$column => DB::raw("DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)")]);
            }
        }
    }
};
