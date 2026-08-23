<?php

declare(strict_types=1);

use Core\App;
use Core\GuidedBuild;
use Core\MarkdownRenderer;
use Core\Request;

$request = App::resolve(Request::class);
$course = GuidedBuild::load();
$requestedSlug = $request->route('lesson');

if ($requestedSlug === null) {
    return view('learn/guided-project-index.view.php', ['course' => $course]);
}

if (!is_string($requestedSlug) || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $requestedSlug) !== 1) {
    abort(404);
}

$lesson = GuidedBuild::find($requestedSlug);
if ($lesson === null) {
    abort(404);
}

$lessonIndex = array_search($lesson['id'], array_column($course['lessons'], 'id'), true);
$previous = is_int($lessonIndex) && $lessonIndex > 0 ? $course['lessons'][$lessonIndex - 1] : null;
$next = is_int($lessonIndex) && isset($course['lessons'][$lessonIndex + 1]) ? $course['lessons'][$lessonIndex + 1] : null;

return view('learn/guided-project-lesson.view.php', [
    'course' => $course,
    'lesson' => $lesson,
    'previous' => $previous,
    'next' => $next,
    'renderedContent' => (new MarkdownRenderer())->render(GuidedBuild::content($lesson), $lesson['title']),
]);
