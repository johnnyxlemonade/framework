<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

final class MetaData
{
    /**
     * @param array<string, string|null> $custom
     * @param array<string, string|null> $extraParams
     * @param array<string, string> $alternates
     */
    public function __construct(
        private readonly ?string $websiteName = null,
        private readonly ?string $charset = null,
        private readonly ?string $viewport = null,
        private readonly ?string $rating = null,
        private readonly ?string $titleSeparator = null,
        private readonly ?string $title = null,
        private readonly ?string $description = null,
        private readonly ?string $keywords = null,
        private readonly ?string $author = null,
        private readonly ?string $robots = null,
        private readonly ?string $canonical = null,
        private readonly ?string $image = null,
        private readonly array $custom = [],
        private readonly array $extraParams = [],
        private readonly array $alternates = [],
    ) {}

    public function withParam(string $key, ?string $value): self
    {
        $params = $this->extraParams;
        $params[$key] = $value;

        return new self(
            websiteName: $this->websiteName,
            charset: $this->charset,
            viewport: $this->viewport,
            rating: $this->rating,
            titleSeparator: $this->titleSeparator,
            title: $this->title,
            description: $this->description,
            keywords: $this->keywords,
            author: $this->author,
            robots: $this->robots,
            canonical: $this->canonical,
            image: $this->image,
            custom: $this->custom,
            extraParams: $params,
            alternates: $this->alternates,
        );
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
            image: $this->image,
            custom: $this->custom,
            extraParams: $this->extraParams,
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
            image: $this->image,
            custom: $this->custom,
            extraParams: $this->extraParams,
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * @return array<string, string|null>
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @return array<string, string|null>
     */
    public function getExtraParams(): array
    {
        return $this->extraParams;
    }

    /**
     * @return array<string, string>
     */
    public function getAlternates(): array
    {
        return $this->alternates;
    }

    public function getCanonicalUrl(): string
    {
        $canonical = $this->canonical ?? '';

        if ($canonical === '' || $this->extraParams === []) {
            return $canonical;
        }

        $filtered = array_filter(
            $this->extraParams,
            static fn(?string $value): bool => $value !== null && $value !== '',
        );

        if ($filtered === []) {
            return $canonical;
        }

        $queryParams = http_build_query($filtered);

        if ($queryParams === '') {
            return $canonical;
        }

        $separator = str_contains($canonical, '?') ? '&' : '?';

        return $canonical . $separator . $queryParams;
    }
}
