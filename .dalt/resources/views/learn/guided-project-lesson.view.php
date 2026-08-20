<?php require base_path('.dalt/resources/views/layouts/head.php') ?>
<?php require base_path('.dalt/resources/views/layouts/learn-nav.php') ?>

<main class="min-h-[calc(100vh-8rem)] bg-[#0a0d12] text-gray-300" id="app" tabindex="-1">
  <div class="mx-auto max-w-4xl px-5 py-10 sm:px-6 sm:py-16">
    <a href="/learn/build" class="inline-flex items-center text-sm font-medium text-gray-400 transition-colors hover:text-[#7dd3fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7dd3fc]"><span class="mr-2" aria-hidden="true">←</span>All Build lessons</a>

    <header class="mt-10 border-b border-[#20303b] pb-9">
      <p class="font-mono text-sm text-[#7dd3fc]">Lesson <?= htmlspecialchars($lesson['id']) ?></p>
      <h1 class="mt-3 max-w-3xl text-4xl font-bold tracking-tight text-gray-50 sm:text-5xl"><?= htmlspecialchars($lesson['title']) ?></h1>
      <p class="mt-5 max-w-2xl text-lg leading-8 text-gray-400"><?= htmlspecialchars($lesson['description']) ?></p>
    </header>

    <?php if ($lesson['background'] !== []): ?>
      <nav class="mt-7 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-[#1e293b] pb-7" aria-label="Helpful background">
        <span class="text-sm text-gray-400">Helpful background:</span>
        <?php foreach ($lesson['background'] as $link): ?><a href="<?= htmlspecialchars($link['href']) ?>" class="text-sm font-semibold text-[#7dd3fc] underline decoration-[#7dd3fc]/30 underline-offset-4 hover:text-[#bae6fd] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7dd3fc]"><?= htmlspecialchars($link['label']) ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <article class="learn-prose prose prose-invert mt-10 max-w-none prose-headings:scroll-mt-24 prose-a:font-medium prose-a:text-[#7dd3fc] prose-a:no-underline hover:prose-a:text-[#bae6fd]">
      <?= $renderedContent ?>
    </article>

    <nav class="mt-16 grid gap-5 border-t border-[#20303b] pt-8 sm:grid-cols-2" aria-label="Build lesson navigation">
      <div><?php if ($previous !== null): ?><a href="<?= htmlspecialchars($previous['route']) ?>" class="block text-sm text-gray-400 hover:text-[#7dd3fc]"><span aria-hidden="true">←</span> Previous<span class="mt-1 block font-bold text-gray-200"><?= htmlspecialchars($previous['title']) ?></span></a><?php endif; ?></div>
      <div class="sm:text-right"><?php if ($next !== null): ?><a href="<?= htmlspecialchars($next['route']) ?>" class="block text-sm text-gray-400 hover:text-[#7dd3fc]">Next <span aria-hidden="true">→</span><span class="mt-1 block font-bold text-gray-200"><?= htmlspecialchars($next['title']) ?></span></a><?php else: ?><a href="/learn/build" class="text-sm font-semibold text-[#7dd3fc] hover:text-[#bae6fd]">Back to all Build lessons <span aria-hidden="true">→</span></a><?php endif; ?></div>
    </nav>
  </div>
</main>
<?php require base_path('.dalt/resources/views/layouts/learn-end.php') ?>
