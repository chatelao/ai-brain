<?php

namespace Tests\Unit\Services;

use App\GitHubService;
use Github\Client;
use Github\Api\Repo;
use Github\Api\Repository\Contents;
use PHPUnit\Framework\TestCase;

class GitHubServiceTest extends TestCase
{
    public function testGetRoadmapFiles(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockRepo = $this->createMock(Repo::class);
        $mockContents = $this->createMock(Contents::class);

        $mockClient->method('api')->with('repo')->willReturn($mockRepo);
        $mockRepo->method('contents')->willReturn($mockContents);

        // Mock contents for root directory
        $mockContents->method('show')
            ->willReturnMap([
                ['chatelao', 'ai-brain', '', null, [
                    ['type' => 'file', 'name' => 'README.md', 'html_url' => 'http://example.com/readme'],
                    ['type' => 'file', 'name' => 'ROADMAP.md', 'html_url' => 'http://example.com/roadmap']
                ]],
                ['chatelao', 'ai-brain', 'docs', null, [
                    ['type' => 'file', 'name' => 'roadmap.rst', 'html_url' => 'http://example.com/roadmap_rst'],
                    ['type' => 'file', 'name' => 'install.rst', 'html_url' => 'http://example.com/install']
                ]]
            ]);

        $service = new GitHubService($mockClient);
        $roadmaps = $service->getRoadmapFiles('chatelao/ai-brain');

        $this->assertCount(2, $roadmaps);
        $this->assertEquals('ROADMAP.md', $roadmaps[0]['name']);
        $this->assertEquals('docs/roadmap.rst', $roadmaps[1]['name']);
    }
}
