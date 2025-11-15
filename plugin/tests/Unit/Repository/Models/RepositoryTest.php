<?php

namespace KobGitUpdater\Tests\Unit\Repository\Models;

use PHPUnit\Framework\TestCase;
use KobGitUpdater\Repository\Models\Repository;
use Brain\Monkey;
use Brain\Monkey\Functions;

class RepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up Brain Monkey for this test
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('sanitize_text_field')->alias(function ($value) {
            return trim(strip_tags($value));
        });
        
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');
        Functions\when('wp_date')->justReturn('2024-01-01 12:00:00');
        Functions\when('human_time_diff')->justReturn('1 hour ago');
        Functions\when('current_time')->alias(function ($arg = 'mysql', $gmt = false) {
            if ($arg === 'timestamp') {
                return 1704110400;
            }
            return '2024-01-01 12:00:00';
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_constructor_creates_valid_repository(): void
    {
        $repository = new Repository(
            'owner',
            'repo',
            'plugin',
            'test-plugin',
            'main',
            false
        );

        $this->assertEquals('owner', $repository->get_owner());
        $this->assertEquals('repo', $repository->get_repo());
        $this->assertEquals('plugin', $repository->get_type());
        $this->assertEquals('test-plugin', $repository->get_slug());
        $this->assertEquals('main', $repository->get_default_branch());
        $this->assertFalse($repository->is_private());
        $this->assertEquals('owner/repo', $repository->get_key());
    }

    public function test_constructor_with_defaults(): void
    {
        $repository = new Repository(
            'owner',
            'repo',
            'theme',
            'test-theme'
        );

        $this->assertEquals('main', $repository->get_default_branch());
        $this->assertFalse($repository->is_private());
    }

    public function test_constructor_validates_owner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository owner cannot be empty');

        new Repository('', 'repo', 'plugin', 'slug');
    }

    public function test_constructor_validates_owner_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository owner format');

        new Repository('invalid-owner-', 'repo', 'plugin', 'slug');
    }

    public function test_constructor_validates_repo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository name cannot be empty');

        new Repository('owner', '', 'plugin', 'slug');
    }

    public function test_constructor_validates_repo_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository name format');

        new Repository('owner', 'invalid@repo', 'plugin', 'slug');
    }

    public function test_constructor_validates_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository type must be "plugin" or "theme"');

        new Repository('owner', 'repo', 'invalid', 'slug');
    }

    public function test_constructor_validates_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository slug cannot be empty');

        new Repository('owner', 'repo', 'plugin', '');
    }

    public function test_constructor_validates_default_branch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default branch cannot be empty');

        new Repository('owner', 'repo', 'plugin', 'slug', '');
    }

    public function test_from_array_creates_repository(): void
    {
        $data = [
            'owner' => 'owner',
            'repo' => 'repo',
            'type' => 'plugin',
            'slug' => 'test-plugin',
            'default_branch' => 'develop',
            'is_private' => true,
            'date_added' => '2024-01-01 12:00:00'
        ];

        $repository = Repository::from_array($data);

        $this->assertEquals('owner', $repository->get_owner());
        $this->assertEquals('repo', $repository->get_repo());
        $this->assertEquals('plugin', $repository->get_type());
        $this->assertEquals('test-plugin', $repository->get_slug());
        $this->assertEquals('develop', $repository->get_default_branch());
        $this->assertTrue($repository->is_private());
        $this->assertEquals('2024-01-01 12:00:00', $repository->get_date_added());
    }

    public function test_from_array_with_missing_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required repository data fields');

        Repository::from_array(['owner' => 'test']);
    }

    public function test_to_array_returns_complete_data(): void
    {
        $repository = new Repository(
            'owner',
            'repo',
            'theme',
            'test-theme',
            'main',
            true
        );

        $array = $repository->to_array();

        $this->assertArrayHasKey('owner', $array);
        $this->assertArrayHasKey('repo', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('default_branch', $array);
        $this->assertArrayHasKey('is_private', $array);
        $this->assertArrayHasKey('date_added', $array);

        $this->assertEquals('owner', $array['owner']);
        $this->assertEquals('repo', $array['repo']);
        $this->assertEquals('theme', $array['type']);
        $this->assertEquals('test-theme', $array['slug']);
        $this->assertEquals('main', $array['default_branch']);
        $this->assertTrue($array['is_private']);
    }

    public function test_get_github_url(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $this->assertEquals(
            'https://github.com/owner/repo',
            $repository->get_github_url()
        );
    }

    public function test_get_display_name(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $this->assertEquals('owner/repo', $repository->get_display_name());
    }

    public function test_is_plugin(): void
    {
        $plugin_repo = new Repository('owner', 'repo', 'plugin', 'slug');
        $theme_repo = new Repository('owner', 'repo', 'theme', 'slug');

        $this->assertTrue($plugin_repo->is_plugin());
        $this->assertFalse($theme_repo->is_plugin());
    }

    public function test_is_theme(): void
    {
        $plugin_repo = new Repository('owner', 'repo', 'plugin', 'slug');
        $theme_repo = new Repository('owner', 'repo', 'theme', 'slug');

        $this->assertFalse($plugin_repo->is_theme());
        $this->assertTrue($theme_repo->is_theme());
    }

    public function test_get_type_label(): void
    {
        $plugin_repo = new Repository('owner', 'repo', 'plugin', 'slug');
        $theme_repo = new Repository('owner', 'repo', 'theme', 'slug');

        $this->assertEquals('Plugin', $plugin_repo->get_type_label());
        $this->assertEquals('Theme', $theme_repo->get_type_label());
    }

    public function test_set_slug(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'old-slug');

        $repository->set_slug('new-slug');

        $this->assertEquals('new-slug', $repository->get_slug());
    }

    public function test_set_slug_validates_empty(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug cannot be empty');

        $repository->set_slug('');
    }

    public function test_set_default_branch(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $repository->set_default_branch('develop');

        $this->assertEquals('develop', $repository->get_default_branch());
    }

    public function test_set_default_branch_validates_empty(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default branch cannot be empty');

        $repository->set_default_branch('');
    }

    public function test_set_is_private(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');

        $repository->set_is_private(true);

        $this->assertTrue($repository->is_private());
    }

    public function test_compare_to_sorts_by_type_first(): void
    {
        $plugin_repo = new Repository('owner', 'repo', 'plugin', 'slug');
        $theme_repo = new Repository('owner', 'repo', 'theme', 'slug');

        $this->assertLessThan(0, $plugin_repo->compare_to($theme_repo));
        $this->assertGreaterThan(0, $theme_repo->compare_to($plugin_repo));
    }

    public function test_compare_to_sorts_by_key_when_same_type(): void
    {
        $repo_a = new Repository('owner', 'aaa', 'plugin', 'slug');
        $repo_z = new Repository('owner', 'zzz', 'plugin', 'slug');

        $this->assertLessThan(0, $repo_a->compare_to($repo_z));
        $this->assertGreaterThan(0, $repo_z->compare_to($repo_a));
    }

    public function test_equals(): void
    {
        $repo1 = new Repository('owner', 'repo', 'plugin', 'slug');
        $repo2 = new Repository('owner', 'repo', 'plugin', 'slug');
        $repo3 = new Repository('owner', 'repo', 'theme', 'slug');
        $repo4 = new Repository('owner', 'repo', 'plugin', 'different-slug');

        $this->assertTrue($repo1->equals($repo2));
        $this->assertFalse($repo1->equals($repo3));
        $this->assertFalse($repo1->equals($repo4));
    }

    public function test_to_json(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug');
        
        $json = $repository->to_json();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('owner', $decoded['owner']);
        $this->assertEquals('repo', $decoded['repo']);
        $this->assertEquals('plugin', $decoded['type']);
    }

    public function test_from_json(): void
    {
        $data = [
            'owner' => 'owner',
            'repo' => 'repo',
            'type' => 'plugin',
            'slug' => 'slug',
            'default_branch' => 'main',
            'is_private' => false,
            'date_added' => '2024-01-01 12:00:00'
        ];

        $json = json_encode($data);
        $repository = Repository::from_json($json);

        $this->assertEquals('owner', $repository->get_owner());
        $this->assertEquals('repo', $repository->get_repo());
        $this->assertEquals('plugin', $repository->get_type());
    }

    public function test_from_json_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON:');

        Repository::from_json('invalid json');
    }

    public function test_to_string(): void
    {
        $repository = new Repository('owner', 'repo', 'plugin', 'slug', 'main');

        $string = (string) $repository;

        $this->assertEquals('owner/repo (plugin): slug [main]', $string);
    }
}