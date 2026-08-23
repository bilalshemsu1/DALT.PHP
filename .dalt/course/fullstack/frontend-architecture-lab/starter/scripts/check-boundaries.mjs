// A dependency-direction check. The compiler happily builds a shared module that
// imports a feature; only a rule like this one can say that direction is wrong.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const srcDir = join(root, 'src');

function walk(directory) {
  return readdirSync(directory).flatMap((entry) => {
    const full = join(directory, entry);

    return statSync(full).isDirectory() ? walk(full) : [full];
  });
}

/** shared | feature:<name> | app — decided by where the file sits, nothing else. */
function layerOf(file) {
  const path = relative(srcDir, file).split('/');

  if (path[0] === 'shared') return { kind: 'shared' };
  if (path[0] === 'features' && path.length > 1) {
    return { kind: 'feature', name: path[1], isPublicSurface: path[2] === 'index.ts' };
  }

  return { kind: 'app' };
}

// `import type` counts: a type-only import is still a direction between modules.
const IMPORT = /(?:^|\n)\s*(?:import|export)\s+(?:type\s+)?[^'"]*from\s+['"]([^'"]+)['"]/g;

function importsOf(file) {
  const source = readFileSync(file, 'utf8');

  return [...source.matchAll(IMPORT)]
    .map((match) => match[1])
    .filter((specifier) => specifier.startsWith('.'));
}

function violationFor(fromFile, toFile) {
  const from = layerOf(fromFile);
  const to = layerOf(toFile);

  if (from.kind === 'shared' && to.kind !== 'shared') {
    return `shared code must not depend on ${to.kind === 'feature' ? `feature '${to.name}'` : 'the app layer'}`;
  }
  if (from.kind === 'feature' && to.kind === 'app') {
    return 'a feature must not depend on the app layer';
  }
  if (from.kind === 'feature' && to.kind === 'feature' && from.name !== to.name && !to.isPublicSurface) {
    return `feature '${from.name}' reaches into the internals of feature '${to.name}'`;
  }

  return null;
}

const violations = [];
let checked = 0;

for (const file of walk(srcDir)) {
  if (!/\.tsx?$/.test(file)) continue;
  checked += 1;

  for (const specifier of importsOf(file)) {
    const resolved = resolve(dirname(file), specifier);
    const target = ['.ts', '.tsx', '/index.ts', '/index.tsx']
      .map((suffix) => resolved + suffix)
      .find((candidate) => {
        try {
          return statSync(candidate).isFile();
        } catch {
          return false;
        }
      });

    if (target === undefined) continue;

    const reason = violationFor(file, target);
    if (reason !== null) {
      violations.push(
        [
          `boundary violation: src/${relative(srcDir, file)}`,
          `  imports src/${relative(srcDir, target)}`,
          `  ${reason}`,
        ].join('\n'),
      );
    }
  }
}

if (violations.length > 0) {
  console.error(violations.join('\n'));
  console.error(`\n${violations.length} boundary violation(s) in ${checked} files.`);
  process.exit(1);
}

console.log(`No boundary violations in ${checked} files.`);
