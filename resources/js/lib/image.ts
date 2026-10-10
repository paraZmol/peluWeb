/** What the server accepts for an uploaded image. */
export const IMAGE_TYPES = 'image/jpeg,image/png,image/webp';
export const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

/** Below this a photo travels as it is. */
const SHRINK_ABOVE_BYTES = 1.5 * 1024 * 1024;
/** Enough to read every line of a receipt. */
const MAX_SIDE = 2000;
const JPEG_QUALITY = 0.85;

/**
 * A phone camera's photo (often above the 5 MB the server takes) scaled
 * down in the browser. Anything that cannot be scaled goes as it came: the
 * server still validates it.
 */
export async function shrinkPhoto(file: File): Promise<File> {
    if (file.size <= SHRINK_ABOVE_BYTES) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
        );

        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', {
            type: 'image/jpeg',
        });
    } catch {
        // Not decodable here (odd format, no memory): let the server judge it.
        return file;
    }
}
