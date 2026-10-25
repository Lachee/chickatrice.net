import { lookup } from 'node:dns/promises';
import { BlockList, isIP } from 'node:net';
import sharp from 'sharp';
import { config } from '#lib/server/config.ts';

/*
 * Despite the column being `avatar_bmp`, Cockatrice loads the avatar with Qt's
 * image readers, so any common format works. Everything is re-encoded as a
 * small PNG: it keeps the row (and what Servatrice sends every client that
 * looks the player up) small, and nothing uploaded reaches the database as-is.
 */

export class AvatarError extends Error {}

export async function encodeAvatar(input: Buffer): Promise<Buffer> {
  if (input.length > config.avatar.maxUploadBytes)
    throw new AvatarError(`That image is too big — the limit is ${Math.floor(config.avatar.maxUploadBytes / 1024 / 1024)} MB.`);

  try {
    const image = sharp(input, { animated: false, limitInputPixels: 50_000_000 });
    // Never enlarged, but always square: a small picture is cropped to its shorter side.
    const { width = 0, height = 0 } = await image.metadata();
    const side = Math.min(config.avatar.size, width, height);
    return await image
      .rotate() // honour EXIF orientation from phone cameras
      .resize(side, side, { fit: 'cover', position: 'attention' })
      .png({ compressionLevel: 9 })
      .toBuffer();
  } catch {
    throw new AvatarError('That file could not be read as an image.');
  }
}

/** Addresses a provider's picture may not point the server at: its own network. */
const PRIVATE = new BlockList();
for (const [ address, prefix ] of [ [ '0.0.0.0', 8 ], [ '10.0.0.0', 8 ], [ '100.64.0.0', 10 ], [ '127.0.0.0', 8 ], [ '169.254.0.0', 16 ], [ '172.16.0.0', 12 ], [ '192.168.0.0', 16 ], [ '224.0.0.0', 3 ] ] as const)
  PRIVATE.addSubnet(address, prefix, 'ipv4');
// IPv4-mapped addresses (::ffff:10.0.0.1) are held to the rules above by BlockList itself.
for (const [ address, prefix ] of [ [ '::', 127 ], [ 'fc00::', 7 ], [ 'fe80::', 10 ], [ 'ff00::', 8 ] ] as const)
  PRIVATE.addSubnet(address, prefix, 'ipv6');

const MAX_REDIRECTS = 3;
const downloadFailed = () => new AvatarError('The provider’s picture could not be downloaded.');

/** Refuses anything but https to a public address. */
async function checkTarget(url: URL) {
  if (url.protocol !== 'https:')
    throw new AvatarError('The provider’s picture is not served over https.');
  const host = url.hostname.replace(/^\[|\]$/g, '');
  const addresses = isIP(host) ? [ { address: host, family: isIP(host) } ] : await lookup(host, { all: true }).catch(() => { throw downloadFailed(); });
  if (addresses.some(({ address, family }) => PRIVATE.check(address, family === 6 ? 'ipv6' : 'ipv4')))
    throw downloadFailed();
}

/** Downloads the picture a provider gave for the user, for "use my provider's avatar". */
export async function fetchAvatar(url: string): Promise<Buffer> {
  let target: URL;
  try {
    target = new URL(url);
  } catch {
    throw new AvatarError('The provider gave an invalid picture address.');
  }

  // The provider's user may control this address, so each hop is checked before it's fetched.
  const signal = AbortSignal.timeout(10_000);
  let response: Response;
  for (let hops = 0; ; hops++) {
    await checkTarget(target);
    response = await fetch(target, { signal, redirect: 'manual' }).catch(() => { throw downloadFailed(); });
    const location = response.headers.get('location');
    if (response.status < 300 || response.status >= 400 || !location) break;
    if (hops === MAX_REDIRECTS) throw downloadFailed();
    target = new URL(location, target);
  }
  if (!response.ok || !response.body)
    throw downloadFailed();

  // Read up to the limit and no further: Content-Length can be missing, or a lie.
  const chunks: Uint8Array[] = [];
  let total = 0;
  const reader = response.body.getReader();
  try {
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      total += value.length;
      if (total > config.avatar.maxUploadBytes) {
        await reader.cancel();
        throw new AvatarError('The provider’s picture is too big.');
      }
      chunks.push(value);
    }
  } catch (e) {
    throw e instanceof AvatarError ? e : downloadFailed();
  }

  return encodeAvatar(Buffer.concat(chunks));
}

/** The MIME type of a stored avatar, which may predate this site and be any format Qt reads. */
export function sniffImageType(data: Buffer): string {
  if (data.subarray(0, 8).equals(Buffer.from([ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ]))) return 'image/png';
  if (data[0] === 0xff && data[1] === 0xd8) return 'image/jpeg';
  if (data.subarray(0, 4).toString('latin1') === 'GIF8') return 'image/gif';
  if (data.subarray(0, 4).toString('latin1') === 'RIFF' && data.subarray(8, 12).toString('latin1') === 'WEBP') return 'image/webp';
  if (data.subarray(0, 2).toString('latin1') === 'BM') return 'image/bmp';
  return 'application/octet-stream';
}
