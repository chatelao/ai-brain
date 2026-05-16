<?php

namespace Tests\Unit\Services;

use App\GitHubService;
use Github\Client;
use Github\Api\Issue;
use Github\Api\Issue\Comments;
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
                ]],
                ['chatelao', 'ai-brain', 'ROADMAP.md', null, [
                    'content' => base64_encode("- [x] Done task\n- [ ] Next task\n- [ ] Another task")
                ]],
                ['chatelao', 'ai-brain', 'docs/roadmap.rst', null, [
                    'content' => base64_encode("* [x] Already done\n* [ ] Still to do")
                ]]
            ]);

        $service = new GitHubService($mockClient);
        $roadmaps = $service->getRoadmapFiles('chatelao/ai-brain');

        $this->assertCount(2, $roadmaps);
        $this->assertEquals('ROADMAP.md', $roadmaps[0]['name']);
        $this->assertEquals('Next task', $roadmaps[0]['next_task']);
        $this->assertEquals('docs/roadmap.rst', $roadmaps[1]['name']);
        $this->assertEquals('Still to do', $roadmaps[1]['next_task']);
    }

    public function testGetIssueComments(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockIssue = $this->createMock(Issue::class);
        $mockComments = $this->createMock(Comments::class);

        $mockClient->method('api')->with('issue')->willReturn($mockIssue);
        $mockIssue->method('comments')->willReturn($mockComments);

        $expectedComments = [
            ['id' => 1, 'body' => 'First comment'],
            ['id' => 2, 'body' => 'Second comment']
        ];

        $mockComments->expects($this->once())
            ->method('all')
            ->with('chatelao', 'ai-brain', 123)
            ->willReturn($expectedComments);

        $service = new GitHubService($mockClient);
        $comments = $service->getIssueComments('chatelao/ai-brain', 123);

        $this->assertEquals($expectedComments, $comments);
    }
}
