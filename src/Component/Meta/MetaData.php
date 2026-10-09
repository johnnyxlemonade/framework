<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

final readonly class MetaData
{
    /**
     * @param array<string, string|null> $custom
     * @param array<string, string> $alternates
     */
    public function __construct(
        private ?string $websiteName = null,
        private ?string $charset = null,
        private ?string $viewport = null,
        private ?string $rating = null,
        private ?string $titleSeparator = null,
        private ?string $title = null,
        private ?string $description = null,
        private ?string $keywords = null,
        private ?string $author = null,
        private ?string $robots = null,
        private ?string $canonical = null,
        private ?string $url = null,
        private ?string $type = null,
        private ?string $locale = null,
        private ?string $image = null,
        private ?string $imageAlt = null,
        private array $custom = [],
        private array $alternates = [],
    ) {
    }

    public function withDefaults(
        string $websiteName,
        string $charset,
        string $viewport,
        string $rating,
        string $titleSeparator,
    ): self {
        $resolvedWebsiteName = $this->websiteName !== null && $this->websiteName !== '' ? $this->websiteName : $websiteName;
        $resolvedCharset = $this->charset !== null && $this->charset !== '' ? $this->charset : $charset;
        $resolvedViewport = $this->viewport !== null && $this->viewport !== '' ? $this->viewport : $viewport;
        $resolvedRating = $this->rating !== null && $this->rating !== '' ? $this->rating : $rating;
        $resolvedTitleSeparator = $this->titleSeparator !== null && $this->titleSeparator !== '' ? $this->titleSeparator : $titleSeparator;

        return new self(
            websiteName: $resolvedWebsiteName,
            charset: $resolvedCharset,
            viewport: $resolvedViewport,
            rating: $resolvedRating,
            titleSeparator: $resolvedTitleSeparator,
            title: $this->title,
            description: $this->description,
            keywords: $this->keywords,
            author: $this->author,
            robots: $this->robots,
            canonical: $this->canonical,
            url: $this->url,
            type: $this->type,
            locale: $this->locale,
            image: $this->image,
            imageAlt: $this->imageAlt,
            custom: $this->custom,
            alternates: $this->alternates,
        );
    }

    public function withTitleSeparator(string $separator): self
    {
        return new self(
            websiteName: $this->websiteName,
            charset: $this->charset,
            viewport: $this->viewport,
            rating: $this->rating,
            titleSeparator: $separator,
            title: $this->title,
            description: $this->description,
            keywords: $this->keywords,
            author: $this->author,
            robots: $this->robots,
            canonical: $this->canonical,
            url: $this->url,
            type: $this->type,
            locale: $this->locale,
            image: $this->image,
            imageAlt: $this->imageAlt,
            custom: $this->custom,
            alternates: $this->alternates,
        );
    }

    public function getCharset(): string
    {
        return (string) $this->charset;
    }

    public function getViewport(): string
    {
        return (string) $this->viewport;
    }

    public function getRating(): string
    {
        return (string) $this->rating;
    }

    public function getWebsiteName(): string
    {
        return (string) $this->websiteName;
    }

    public function getTitleSeparator(): string
    {
        return (string) $this->titleSeparator;
    }

    public function getTitle(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title . (string) $this->titleSeparator . (string) $this->websiteName;
        }

        return (string) $this->websiteName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getRobots(): ?string
    {
        return $this->robots;
    }

    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    /**
     * @return array<string, string|null>
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @return array<string, string>
     */
    public function getAlternates(): array
    {
        return $this->alternates;
    }

}
