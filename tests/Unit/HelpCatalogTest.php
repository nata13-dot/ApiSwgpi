<?php

namespace Tests\Unit;

use App\Support\HelpCatalog;
use Tests\TestCase;

class HelpCatalogTest extends TestCase
{
    private const AUDIENCES = ['guest', 'all', 'student', 'teacher', 'manager', 'academic', 'admin', 'general'];

    public function test_each_topic_is_complete_and_its_links_exist(): void
    {
        $topics = HelpCatalog::topics();
        $this->assertGreaterThan(50, count($topics));

        foreach ($topics as $id => $topic) {
            $this->assertArrayHasKey($topic['category'], HelpCatalog::CATEGORIES, "{$id}: categoría");
            $this->assertEmpty(array_diff((array) $topic['audience'], self::AUDIENCES), "{$id}: audiencia");
            $this->assertNotSame('', trim($topic['question']), "{$id}: pregunta");
            $this->assertTrue(!empty($topic['steps']) || !empty($topic['dynamic']), "{$id}: sin pasos");
            foreach (array_filter([$topic['next'] ?? null, ...($topic['related'] ?? [])]) as $linked) {
                $this->assertArrayHasKey($linked, $topics, "{$id}: enlaza a {$linked}");
            }
        }
    }

    public function test_every_screen_exists_in_both_fronts(): void
    {
        $root = dirname(base_path());
        if (!is_dir("{$root}/React_Swgpi") || !is_dir("{$root}/Frontend_Swgpi")) {
            $this->markTestSkipped('Los fronts no están junto a la API.');
        }
        $routes = file_get_contents("{$root}/React_Swgpi/src/App.jsx");

        foreach (HelpCatalog::topics() as $id => $topic) {
            if (isset($topic['app'])) {
                $path = strtok($topic['app'], '?');
                $this->assertTrue(str_contains($routes, "'{$path}'") || str_contains($routes, "\"{$path}\""), "{$id}: ruta de la app {$path}");
            }
            if (isset($topic['web'])) {
                $this->assertFileExists("{$root}/Frontend_Swgpi".strtok(strtok($topic['web'], '?'), '#'), "{$id}: página web");
            }
        }
    }

    public function test_personal_answers_have_a_handler(): void
    {
        foreach (HelpCatalog::topics() as $id => $topic) {
            if (!empty($topic['dynamic'])) {
                $this->assertTrue(method_exists(\App\Services\HelpPersonalDataService::class, $topic['dynamic']), "{$id}: {$topic['dynamic']}");
            }
        }
    }
}
