<?php

declare(strict_types=1);

namespace Core;

final class GuidedBuild
{
    /**
     * @return array{
     *   title: string,
     *   description: string,
     *   lessons: list<array{id: string, slug: string, title: string, description: string, background: list<array{label: string, href: string}>}>
     * }
     */
    public static function load(?string $courseRoot = null): array
    {
        $root = rtrim($courseRoot ?? base_path('.dalt/course'), '/\\');
        $manifestPath = $root . '/guided-build.php';
        if (!is_file($manifestPath)) {
            throw new CourseMetadataException('The guided Build manifest is missing: .dalt/course/guided-build.php');
        }

        $course = require $manifestPath;
        if (!is_array($course) || !is_string($course['title'] ?? null) || !is_string($course['description'] ?? null)) {
            throw new CourseMetadataException('The guided Build manifest must declare a title and description.');
        }

        $lessons = $course['lessons'] ?? null;
        if (!is_array($lessons) || !array_is_list($lessons)) {
            throw new CourseMetadataException('The guided Build manifest lessons must be a list.');
        }

        $seenIds = [];
        $seenSlugs = [];
        foreach ($lessons as $index => &$lesson) {
            if (!is_array($lesson)) {
                throw new CourseMetadataException("Guided Build lesson at index {$index} must be an array.");
            }

            $id = $lesson['id'] ?? null;
            $slug = $lesson['slug'] ?? null;
            if (!is_string($id) || preg_match('/\A[0-9]{2}\z/D', $id) !== 1) {
                throw new CourseMetadataException("Guided Build lesson at index {$index} needs a two-digit id.");
            }
            if (!is_string($slug) || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) !== 1) {
                throw new CourseMetadataException("Guided Build lesson {$id} has an invalid slug.");
            }
            if (isset($seenIds[$id]) || isset($seenSlugs[$slug])) {
                throw new CourseMetadataException("Guided Build lesson {$id} duplicates an id or slug.");
            }
            foreach (['title', 'description'] as $field) {
                if (!is_string($lesson[$field] ?? null) || trim($lesson[$field]) === '') {
                    throw new CourseMetadataException("Guided Build lesson {$id} needs a non-empty {$field}.");
                }
            }

            $background = $lesson['background'] ?? [];
            if (!is_array($background) || !array_is_list($background)) {
                throw new CourseMetadataException("Guided Build lesson {$id} background links must be a list.");
            }
            foreach ($background as $link) {
                if (!is_array($link) || !is_string($link['label'] ?? null) || !is_string($link['href'] ?? null)) {
                    throw new CourseMetadataException("Guided Build lesson {$id} has an invalid background link.");
                }
            }

            $directory = $root . '/guided-build/' . $id . '-' . $slug;
            if (!is_file($directory . '/README.md')) {
                throw new CourseMetadataException("Guided Build lesson {$id} has no README.md: {$directory}");
            }

            $lesson['background'] = $background;
            $lesson['path'] = $directory . '/README.md';
            $lesson['route'] = self::routeFor($slug);
            $seenIds[$id] = true;
            $seenSlugs[$slug] = true;
        }
        unset($lesson);

        $course['lessons'] = $lessons;

        return $course;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug, ?string $courseRoot = null): ?array
    {
        foreach (self::load($courseRoot)['lessons'] as $lesson) {
            if ($lesson['slug'] === $slug) {
                return $lesson;
            }
        }

        return null;
    }

    public static function content(array $lesson): string
    {
        $content = file_get_contents($lesson['path']);
        if ($content === false) {
            throw new CourseMetadataException("Guided Build lesson {$lesson['id']} could not be read.");
        }

        return $content;
    }

    public static function routeFor(string $slug): string
    {
        return '/learn/build/' . $slug;
    }
}
