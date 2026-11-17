<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * SOLID (Single Responsibility): this class only moves files to and from the upload folder.
 * It knows nothing about products, so it could store user avatars tomorrow unchanged.
 */
final class FileUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/products')]
        private readonly string $targetDirectory,
    ) {
    }

    /** @return string the generated file name (random, so two uploads never overwrite each other) */
    public function upload(UploadedFile $file): string
    {
        $fileName = bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?? 'bin');
        $file->move($this->targetDirectory, $fileName);

        return $fileName;
    }

    public function remove(?string $fileName): void
    {
        // basename() blocks "../" tricks even though names come from our own database.
        if (null !== $fileName && is_file($path = $this->targetDirectory.'/'.basename($fileName))) {
            unlink($path);
        }
    }
}
