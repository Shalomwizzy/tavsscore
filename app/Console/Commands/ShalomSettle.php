<?php
namespace App\Console\Commands;
use App\Services\ShalomAIService;
use Illuminate\Console\Command;
class ShalomSettle extends Command { protected $signature = 'shalom:settle'; protected $description = 'Settle private Shalom AI predictions against final scores.'; public function handle(ShalomAIService $ai): int { $n=$ai->settle(); $this->info("Shalom AI settled {$n} prediction(s)."); return self::SUCCESS; } }
