<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\GitHubService;
use Github\Client;
use Github\Api\Issue;
use Github\Api\Issue\Comments;

class GitHubServiceTest extends TestCase
{
    public function testPostComment()
    {
        $mockComments = $this->createMock(Comments::class);
        $mockComments->expects($this->once())
            ->method('create')
            ->with('owner', 'repo', 123, ['body' => 'Test comment'])
            ->willReturn(['id' => 1]);

        $mockIssue = $this->createMock(Issue::class);
        $mockIssue->method('comments')->willReturn($mockComments);

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('api')->with('issue')->willReturn($mockIssue);

        $service = new GitHubService($mockClient);
        $result = $service->postComment('owner/repo', 123, 'Test comment');

        $this->assertEquals(['id' => 1], $result);
    }

    public function testPostCommentInvalidRepo()
    {
        $service = new GitHubService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid repository name: invalid-repo");
        $service->postComment('invalid-repo', 123, 'Test comment');
    }
}
