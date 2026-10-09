<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

/**
 * Carries page-specific metadata independently from its HTML rendering.
 *
 * Instances are immutable; modifier methods return a copy while preserving all other values.
 */
final readonly class MetaData
{
    /**
     * Initializes page metadata and optional custom or alternate declarations.
     *
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

    /**
     * Returns a copy where empty standard values fall back to the supplied component defaults.
     */
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

    /**
     * Returns a copy with a title separator specific to this page.
     */
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

    /**
     * Returns the page character encoding or an empty string when it is unset.
     */
    public function getCharset(): string
    {
        return (string) $this->charset;
    }

    /**
     * Returns the viewport declaration or an empty string when it is unset.
     */
    public function getViewport(): string
    {
        return (string) $this->viewport;
    }

    /**
     * Returns the content rating or an empty string when it is unset.
     */
    public function getRating(): string
    {
        return (string) $this->rating;
    }

    /**
     * Returns the website name or an empty string when it is unset.
     */
    public function getWebsiteName(): string
    {
        return (string) $this->websiteName;
    }

    /**
     * Returns the title separator or an empty string when it is unset.
     */
    public function getTitleSeparator(): string
    {
        return (string) $this->titleSeparator;
    }

    /**
     * Returns the page title combined with the website name when a page title is present.
     */
    public function getTitle(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title . (string) $this->titleSeparator . (string) $this->websiteName;
        }

        return (string) $this->websiteName;
    }

    /**
     * Returns the optional page description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Returns the optional page keywords.
     */
    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    /**
     * Returns the optional author attribution.
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * Returns the optional robots directive.
     */
    public function getRobots(): ?string
    {
        return $this->robots;
    }

    /**
     * Returns the optional canonical URL.
     */
    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    /**
     * Returns the optional page URL used by social metadata.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Returns the optional social metadata type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Returns the optional locale used by social metadata.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Returns the optional image URL used by metadata sections.
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Returns the optional alternative text for the metadata image.
     */
    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    /**
     * Returns custom metadata keys and their optional values.
     *
     * @return array<string, string|null>
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * Returns alternate language codes mapped to their URLs.
     *
     * @return array<string, string>
     */
    public function getAlternates(): array
    {
        return $this->alternates;
    }

}
