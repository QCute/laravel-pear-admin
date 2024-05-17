<?php

namespace App\Admin\Models\ChargeStatistic;

use App\Admin\Models\Extend\DistributionModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LtvModel extends DistributionModel 
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'remote';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role';

    public static function getDaily(int $begin, int $end, array $range): Collection
    {
        $builder = null;
        $model = new static();
        for($date = $begin; $date < $end; $date += 86400) {

            // role number
            $base = $model
                ->whereBetween("role.register_time", [$date, $date + 86400])
                ->select([
                    DB::raw("'" . date('Y-m-d', $date) . "' AS `date`"),
                    DB::raw("COUNT(role.`role_id`) AS `number`"),
                ]);

            // row base
            $row = $model->getConnection()->table($base, 'base');

            foreach($range as $day) {

                // each day
                $sub = $model
                    ->leftJoin('charge_order', 'role.role_id', '=', 'charge_order.role_id')
                    ->whereBetween("role.register_time", [$date, $date + 86400]) //date
                    ->whereBetween('charge_order.time', [$date, $date + ($day + 1) * 86400]) // date total offset day
                    ->select([
                        // DB::raw("CONCAT(IFNULL(SUM(`money`), 0), '/', COUNT(role.`role_id`), '(', FORMAT(IFNULL(IFNULL(SUM(`money`), 0) * 100 / COUNT(role.`role_id`), 0), 2), '%', ')') AS `day_$day`"),
                        DB::raw("IFNULL(SUM(`money`), 0) AS `outer_$day`"), 
                        DB::raw("COUNT(role.`role_id`) AS `inner_$day`"),
                    ]);

                // row sub
                $row->joinSub($sub, "sub_$day", function() {});
            }

            // union all row
            $builder = $builder ? $builder->unionAll($row) : $row;
        }

        $data = $builder ? $builder->groupBy('date')->get() : collect();

        foreach($data as $row) {
            foreach($range as $day) {
                if($row->{"inner_$day"} == 0) {
                    $ratio = '0.00%';
                } else {
                    $ratio = number_format($row->{"outer_$day"} / $row->{"inner_$day"}, 2) . '%';
                }
                $row->{"day_$day"} = $ratio;
            }
        }

        return $data;
    }
}