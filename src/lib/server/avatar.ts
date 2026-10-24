import sharp from 'sharp';
import { config } from '#lib/server/config.js';

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

  const size = config.avatar.size;
  try {
    return await sharp(input, { animated: false, limitInputPixels: 50_000_000 })
      .rotate() // honour EXIF orientation from phone cameras
      .resize(size, size, { fit: 'cover', position: 'attention', withoutEnlargement: true })
      .png({ compressionLevel: 9 })
      .toBuffer();
  } catch {
    throw new AvatarError('That file could not be read as an image.');
  }
}

/** Downloads the picture a provider gave for the user, for "use my provider's avatar". */
export async function fetchAvatar(url: string): Promise<Buffer> {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    throw new AvatarError('The provider gave an invalid picture address.');
  }
  if (parsed.protocol !== 'https:')
    throw new AvatarError('The provider’s picture is not served over https.');

  const response = await fetch(parsed, { signal: AbortSignal.timeout(10_000), redirect: 'follow' })
    .catch(() => { throw new AvatarError('The provider’s picture could not be downloaded.'); });
  if (!response.ok)
    throw new AvatarError(`The provider’s picture could not be downloaded (${response.status}).`);

  const length = Number(response.headers.get('content-length') ?? 0);
  if (length > config.avatar.maxUploadBytes)
    throw new AvatarError('The provider’s picture is too big.');

  return encodeAvatar(Buffer.from(await response.arrayBuffer()));
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
