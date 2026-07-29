<?php
namespace App\Console\Commands;
use App\Services\ShalomAIService;
use Illuminate\Console\Command;
class ShalomDraft extends Command { protected $signature = 'shalom:draft'; protected $description = 'Create an admin-only Shalom AI football editorial draft.'; public function handle(ShalomAIService $ai): int { $draft=$ai->makeBlogDraft(); if (!$draft) {$this->warn('No Shalom AI forecast is ready for a draft.'); return self::SUCCESS;} $this->info("Created private draft #{$draft->id}."); return self::SUCCESS; } }
