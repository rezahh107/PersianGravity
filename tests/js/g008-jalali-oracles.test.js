/**
 * G-008 differential verification.
 *
 * Reference implementation lineage: jalaali-js 2.0.1,
 * commit 7ff10a0a4145c84a6911e87bfacf40ddf51a2adc, MIT License.
 * Copyright (c) 2020 Behrang Norouzinia.
 */
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
const div = (a, b) => Math.trunc(a / b);
const mod = (a, b) => a - Math.trunc(a / b) * b;

function jalCalCore(jy) {
  if (jy < BREAKS[0] || jy > BREAKS.at(-1) - 1) throw new RangeError('jalaali-js range');
  const gy = jy + 621;
  let leapJ = -14;
  let jp = BREAKS[0];
  let jm = 0;
  let jump = 0;
  for (let i = 1; i < BREAKS.length; i += 1) {
    jm = BREAKS[i];
    jump = jm - jp;
    if (jy < jm) break;
    leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
    jp = jm;
  }
  const n = jy - jp;
  leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
  if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  return { gy, march: 20 + leapJ - leapG, jump, n };
}

function leapFromCycle(jump, n) {
  let adjusted = n;
  if (jump - n < 6) adjusted = n - jump + div(jump + 4, 33) * 33;
  let leap = mod(mod(adjusted + 1, 33) - 1, 4);
  if (leap === -1) leap = 4;
  return leap;
}

function jalCal(jy) {
  const r = jalCalCore(jy);
  return { leap: leapFromCycle(r.jump, r.n), gy: r.gy, march: r.march };
}

function g2d(gy, gm, gd) {
  let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
  d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
  return d;
}

function d2g(jdn) {
  let j = 4 * jdn + 139361631;
  j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div(mod(j, 1461), 4) * 5 + 308;
  const gd = div(mod(i, 153), 5) + 1;
  const gm = mod(div(i, 153), 12) + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return { gy, gm, gd };
}

function toJalaali(gy, gm, gd) {
  const jdn = g2d(gy, gm, gd);
  const gy2 = d2g(jdn).gy;
  let jy = Math.min(gy2 - 621, 3177);
  const r = jalCal(jy);
  const jdn1f = g2d(r.gy, 3, r.march);
  let k = jdn - jdn1f;
  if (k >= 0) {
    if (k <= 185) return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
    k -= 186;
  } else {
    jy -= 1;
    k += 179;
    if (r.leap === 1) k += 1;
  }
  return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

function parseGregorian(key) {
  const [gy, gm, gd] = key.split('-').map(Number);
  return { gy, gm, gd };
}

const icuFormatter = new Intl.DateTimeFormat('en-u-ca-persian-nu-latn', {
  timeZone: 'UTC', year: 'numeric', month: 'numeric', day: 'numeric',
});

function icuPersian(key) {
  const { gy, gm, gd } = parseGregorian(key);
  const date = new Date(Date.UTC(gy, gm - 1, gd));
  const parts = Object.fromEntries(
    icuFormatter.formatToParts(date)
      .filter((part) => ['year', 'month', 'day'].includes(part.type))
      .map((part) => [part.type, Number(part.value)]),
  );
  return { jy: parts.year, jm: parts.month, jd: parts.day };
}

test('PHP production converter matches locked jalaali-js lineage and ICU over VALIDATED_PRODUCT_RANGE', () => {
  const proc = spawnSync(
    'php',
    ['tests/g008/php-reference-dump.php', '1800-01-01', '2124-03-19'],
    { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
  );
  assert.equal(proc.status, 0, proc.stderr || 'PHP oracle bridge failed');
  const rows = JSON.parse(proc.stdout);
  assert.ok(rows.length > 118000, `unexpected case count ${rows.length}`);

  for (const [key, jy, jm, jd] of rows) {
    const g = parseGregorian(key);
    const reference = toJalaali(g.gy, g.gm, g.gd);
    const icu = icuPersian(key);
    assert.deepEqual({ jy, jm, jd }, reference, `jalaali-js mismatch at ${key}`);
    assert.deepEqual({ jy, jm, jd }, icu, `ICU ${process.versions.icu} mismatch at ${key}`);
  }
});

test('the first day outside V1 range exposes the current ICU/Borkowski divergence', () => {
  const key = '2124-03-20';
  const g = parseGregorian(key);
  assert.deepEqual(toJalaali(g.gy, g.gm, g.gd), { jy: 1502, jm: 12, jd: 30 });
  assert.deepEqual(icuPersian(key), { jy: 1503, jm: 1, jd: 1 });
});
