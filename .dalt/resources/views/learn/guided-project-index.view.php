<?php require base_path('.dalt/resources/views/layouts/head.php') ?>
<?php require base_path('.dalt/resources/views/layouts/learn-nav.php') ?>

<main class="min-h-[calc(100vh-8rem)] bg-[#0a0d12] text-gray-300" id="app" tabindex="-1">
  <div class="mx-auto max-w-4xl px-5 py-10 sm:px-6 sm:py-16">
    <a href="/learn" class="inline-flex items-center text-sm font-medium text-gray-400 transition-colors hover:text-[#7dd3fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7dd3fc]"><span class="mr-2" aria-hidden="true">←</span>Learning dashboard</a>

    <header class="mt-10 border-b border-[#20303b] pb-10">
      <h1 class="max-w-3xl text-4xl font-bold tracking-tight text-gray-50 sm:text-5xl"><?= htmlspecialchars($course['title']) ?></h1>
      <p class="mt-5 max-w-2xl text-lg leading-8 text-gray-400"><?= htmlspecialchars($course['description']) ?></p>
      <p class="mt-6 max-w-2xl text-sm leading-6 text-gray-400">We begin with a clean framework and keep improving the same application. Every published lesson leaves it working and gives us one useful new capability.</p>
    </header>

    <section class="mt-12" aria-labelledby="build-lessons">
      <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#1e293b] pb-5">
        <div>
          <h2 id="build-lessons" class="text-2xl font-bold text-gray-100">Build the application</h2>
          <p class="mt-2 text-sm leading-6 text-gray-400">Follow the lessons in order. Helpful background is linked, never required.</p>
        </div>
        <p class="text-sm text-gray-400"><?= count($course['lessons']) ?> published</p>
      </div>

      <?php if ($course['lessons'] === []): ?>
        <div class="py-12">
          <h3 class="text-lg font-bold text-gray-200">The first lesson is being built now.</h3>
          <p class="mt-3 max-w-2xl leading-7 text-gray-400">We are practicing the complete setup and first browser-visible change before turning it into course material.</p>
        </div>
      <?php else: ?>
        <ol class="divide-y divide-[#1e293b]">
          <?php foreach ($course['lessons'] as $lesson): ?>
            <li>
              <a href="<?= htmlspecialchars($lesson['route']) ?>" class="group grid gap-3 py-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7dd3fc] sm:grid-cols-[3rem_1fr_auto] sm:items-start sm:gap-5">
                <span class="font-mono text-sm text-[#7dd3fc]" aria-hidden="true"><?= htmlspecialchars($lesson['id']) ?></span>
                <span><span class="block text-lg font-bold text-gray-200 group-hover:text-[#bae6fd]"><?= htmlspecialchars($lesson['title']) ?></span><span class="mt-1 block max-w-2xl text-sm leading-6 text-gray-400"><?= htmlspecialchars($lesson['description']) ?></span></span>
                <span class="text-gray-600 transition-transform group-hover:translate-x-1 group-hover:text-[#7dd3fc]" aria-hidden="true">→</span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require base_path('.dalt/resources/views/layouts/learn-end.php') ?>
