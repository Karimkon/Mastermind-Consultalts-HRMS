<?php

namespace App\Console\Commands;

use App\Services\BlogService;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish blog posts whose scheduled time has arrived and ping IndexNow';

    public function handle(BlogService $blog): int
    {
        $count = $blog->releaseScheduled();

        $this->info($count === 0 ? 'No scheduled posts were due.' : "Published {$count} scheduled post(s).");

        return self::SUCCESS;
    }
}
