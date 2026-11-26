/**
 * Platform display helpers: a short label and a generic device family.
 * (Generic icons on purpose; console makers' logos are trademarks.)
 */

export type Family = 'console' | 'handheld' | 'desktop' | 'mobile' | 'vr' | 'web' | 'other';

const SHORT: [RegExp, string][] = [
  [/^PlayStation 5$/i, 'PS5'],
  [/^PlayStation 4$/i, 'PS4'],
  [/PlayStation VR2/i, 'PS VR2'],
  [/Xbox Series/i, 'Xbox Series'],
  [/^Xbox One$/i, 'Xbox One'],
  [/Nintendo Switch 2/i, 'Switch 2'],
  [/Nintendo Switch/i, 'Switch'],
  [/PC \(Microsoft Windows\)|^Windows$/i, 'PC'],
  [/^Mac$|macOS/i, 'Mac'],
  [/^Linux$/i, 'Linux'],
  [/^iOS$/i, 'iOS'],
  [/^Android$/i, 'Android'],
  [/Meta Quest|Oculus Quest/i, 'Quest'],
  [/SteamVR|Steam VR/i, 'SteamVR'],
  [/Web browser/i, 'Browser'],
];

export function shortName(name: string): string {
  return SHORT.find(([re]) => re.test(name))?.[1] ?? name;
}

export function family(name: string): Family {
  if (/VR|Quest|Oculus/i.test(name)) return 'vr';
  if (/Switch|Steam Deck|Vita|3DS|Game Boy/i.test(name)) return 'handheld';
  if (/PlayStation|Xbox|Wii|GameCube|Dreamcast|Saturn|Genesis|NES/i.test(name)) return 'console';
  if (/PC|Windows|Mac|Linux|DOS/i.test(name)) return 'desktop';
  if (/iOS|Android|iPad|Mobile/i.test(name)) return 'mobile';
  if (/Web|Browser/i.test(name)) return 'web';
  return 'other';
}
