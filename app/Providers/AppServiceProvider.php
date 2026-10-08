<?php

namespace App\Providers;

use App\Support\ExtensionMimeTypeGuesser;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter as FlysystemLocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\Mime\MimeTypes;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        MimeTypes::getDefault()->registerGuesser(new ExtensionMimeTypeGuesser());

        // cPanel PHP has no fileinfo, so Flysystem must not construct `finfo`.
        Storage::extend('local', function ($app, $config) {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
            );

            $links = ($config['links'] ?? null) === 'skip'
                ? FlysystemLocalAdapter::SKIP_LINKS
                : FlysystemLocalAdapter::DISALLOW_LINKS;

            $adapter = new FlysystemLocalAdapter(
                $config['root'],
                $visibility,
                $config['lock'] ?? LOCK_EX,
                $links,
                mimeTypeDetector: new ExtensionMimeTypeDetector(),
                lazyRootCreation: $config['lazy_root_creation'] ?? false,
            );

            return (new LocalFilesystemAdapter(
                new Flysystem($adapter, Arr::only($config, [
                    'directory_visibility',
                    'disable_asserts',
                    'retain_visibility',
                    'temporary_url',
                    'url',
                    'visibility',
                ])),
                $adapter,
                $config
            ))->shouldServeSignedUrls(
                $config['serve'] ?? false,
                fn () => $app['url'],
            );
        });
    }
}
