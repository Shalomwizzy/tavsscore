<?php
namespace App\Console\Commands;
use App\Services\ShalomAIService;
use Illuminate\Console\Command;
class ShalomPredict extends Command { protected $signature = 'shalom:predict {--hours-ahead=48}'; protected $description = 'Generate private Shalom AI shadow predictions.'; public function handle(ShalomAIService $ai): int { $r=$ai->predictUpcoming((int)$this->option('hours-ahead')); $this->info("Shalom AI: {$r['created']} prediction(s), {$r['skipped']} skipped."); return self::SUCCESS; } }
