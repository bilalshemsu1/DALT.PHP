<?php require base_path('.dalt/resources/views/layouts/head.php') ?>
<?php require base_path('.dalt/resources/views/layouts/learn-nav.php') ?>
<?php
$sectionsByTrack = ['core' => [], 'fullstack' => []];
foreach ($sections as $sectionId => $section) { $sectionsByTrack[$section['track']][$sectionId] = $section; }
?>

<main class="min-h-[calc(100vh-8rem)] bg-[#0a0d12] text-gray-300" id="app" tabindex="-1">
  <div class="mx-auto max-w-5xl px-5 py-10 sm:px-6 sm:py-16">
    <header class="max-w-2xl"><p class="font-mono text-xs font-semibold uppercase tracking-[0.16em] text-[#93DA97]">DALT.PHP learning</p><h1 class="mt-4 text-4xl font-bold tracking-tight text-gray-50 sm:text-5xl">Keep building your backend instincts.</h1><p class="mt-5 text-lg leading-8 text-gray-400">Study backend fundamentals, follow the Fullstack curriculum, or build one real application with us from the ground up.</p></header>

    <div class="mt-12 grid gap-8 lg:grid-cols-2">
      <?php foreach (['core' => ['title' => 'DALT Core', 'intro' => 'Backend concepts and debugging practice.', 'accent' => '#93DA97', 'route' => null], 'fullstack' => ['title' => 'DALT Fullstack', 'intro' => 'One deliberate journey across browser, React, DALT, and PostgreSQL.', 'accent' => '#c4a7ff', 'route' => '/learn/fullstack']] as $trackId => $definition): ?>
        <?php $track = $tracks[$trackId]; $total = count($track['lessons']); $progress = $total === 0 ? 0 : (int) round($track['completed_count'] / $total * 100); ?>
        <section class="border-t border-[#1e293b] pt-6" aria-labelledby="<?= $trackId ?>-title">
          <p class="font-mono text-xs font-semibold uppercase tracking-[0.16em]" style="color: <?= $definition['accent'] ?>"><?= $trackId === 'core' ? 'Established curriculum' : 'New learning journey' ?></p>
          <h2 id="<?= $trackId ?>-title" class="mt-3 text-2xl font-bold text-gray-100"><?= $definition['title'] ?></h2><p class="mt-2 min-h-12 text-sm leading-6 text-gray-500"><?= $definition['intro'] ?></p>
          <div class="mt-6 flex items-center justify-between gap-4"><p class="font-mono text-sm text-gray-400"><?= $track['completed_count'] ?> / <?= $total ?> lessons</p><p class="font-mono text-xs text-gray-600"><?= $progress ?>%</p></div><div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#1e293b]" role="progressbar" aria-label="<?= $definition['title'] ?> progress" aria-valuemin="0" aria-valuemax="<?= $total ?>" aria-valuenow="<?= $track['completed_count'] ?>"><div class="h-full rounded-full" style="width: <?= $progress ?>%; background: <?= $definition['accent'] ?>"></div></div>
          <?php if ($trackId === 'core' && $currentChallenge !== null): ?><a href="/learn/challenges/<?= htmlspecialchars($currentChallenge['id']) ?>" class="mt-6 inline-flex items-center text-sm font-bold text-[#93DA97] transition-colors hover:text-[#b5edb8]">Active challenge: Continue <?= htmlspecialchars($currentChallenge['title']) ?><span class="ml-2" aria-hidden="true">→</span></a><?php elseif ($track['continuation'] !== null): ?><a href="/learn/lessons/<?= htmlspecialchars($track['continuation']['id']) ?>" class="mt-6 inline-flex items-center text-sm font-bold transition-colors hover:opacity-80" style="color: <?= $definition['accent'] ?>"><?= $track['completed_count'] === 0 ? 'Begin with' : 'Continue' ?> <?= htmlspecialchars($track['continuation']['title']) ?><span class="ml-2" aria-hidden="true">→</span></a><?php elseif ($trackId === 'core'): ?><p class="mt-6 font-semibold text-[#93DA97]">All lessons complete</p><?php endif; ?>
          <?php if ($definition['route'] !== null): ?><a href="<?= $definition['route'] ?>" class="ml-5 mt-6 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-200">View journey <span class="ml-2" aria-hidden="true">→</span></a><?php endif; ?>
          <?php if ($trackId === 'core'): ?><ol class="mt-8 divide-y divide-[#1e293b] border-y border-[#1e293b]"><?php foreach ($sectionsByTrack['core'] as $sectionId => $section): ?><?php $sectionLessons = array_values(array_filter($track['lessons'], fn (array $lesson): bool => $lesson['section'] === $sectionId)); $complete = count(array_filter($sectionLessons, fn (array $lesson): bool => isset($completedLessonIds[$lesson['id']]))); ?><li><a href="/learn/tracks/<?= $sectionId ?>" class="group flex items-center gap-4 py-4 transition-colors hover:bg-[#11161d] sm:px-3"><span class="font-mono text-xs text-gray-600"><?= str_pad((string) $section['display_order'], 2, '0', STR_PAD_LEFT) ?></span><span class="min-w-0 flex-1"><span class="block font-semibold text-gray-200 group-hover:text-[#93DA97]"><?= htmlspecialchars($section['title']) ?></span><span class="mt-1 block text-sm text-gray-500"><?= htmlspecialchars($section['description']) ?></span></span><span class="font-mono text-xs text-gray-500"><?= $complete ?> / <?= count($sectionLessons) ?></span></a></li><?php endforeach; ?></ol><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
    <section class="mt-12 grid gap-6 border-y border-[#20303b] py-8 sm:grid-cols-[1fr_auto] sm:items-center" aria-labelledby="guided-build-title">
      <div>
        <h2 id="guided-build-title" class="text-2xl font-bold text-gray-100">DALT Build</h2>
        <p class="mt-2 max-w-2xl leading-7 text-gray-400">A guided, continuous issue-tracker project. We start with the clean framework and add React, DALT APIs, PostgreSQL, and Docker when the application needs them.</p>
      </div>
      <a href="/learn/build" class="inline-flex items-center justify-center rounded-lg bg-sky-300 px-4 py-2.5 text-sm font-bold text-slate-950 transition-colors hover:bg-sky-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-300">Open Build <span class="ml-2" aria-hidden="true">→</span></a>
    </section>
    <section class="mt-14 flex flex-col justify-between gap-5 border-t border-[#1e293b] pt-8 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-gray-100">Want to browse everything?</h2><p class="mt-1 text-sm text-gray-500">All lessons and Core debugging challenges live in one resource library.</p></div><a href="/learn/resources" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-200 transition-colors hover:border-[#93DA97]/50 hover:text-[#93DA97]">Open resources <span class="ml-2" aria-hidden="true">→</span></a></section>
  </div>
</main>
<?php require base_path('.dalt/resources/views/layouts/learn-end.php') ?>
