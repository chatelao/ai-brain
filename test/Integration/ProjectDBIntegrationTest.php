<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Project;
use PDO;

class ProjectDBIntegrationTest extends TestCase
{
    private $db;
    private $pdo;
    private $projectModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            avatar VARCHAR(255),
            github_token VARCHAR(255),
            github_username VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NOT NULL,
            github_repo VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $this->db = $this->createMock(Database::class);
        $this->db->method('getConnection')->willReturn($this->pdo);

        $this->projectModel = new Project($this->db);
    }

    public function testCreateProject()
    {
        $userId = 1;
        $repo = 'owner/repo';

        $result = $this->projectModel->create($userId, $repo);
        $this->assertTrue($result);

        $projects = $this->projectModel->findByUserId($userId);
        $this->assertCount(1, $projects);
        $this->assertEquals($repo, $projects[0]['github_repo']);
    }

    public function testDeleteProject()
    {
        $userId = 1;
        $repo = 'owner/repo';
        $this->projectModel->create($userId, $repo);
        $projects = $this->projectModel->findByUserId($userId);
        $projectId = $projects[0]['id'];

        $result = $this->projectModel->delete($projectId, $userId);
        $this->assertTrue($result);

        $projects = $this->projectModel->findByUserId($userId);
        $this->assertCount(0, $projects);
    }
}
