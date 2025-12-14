<?php

declare(strict_types=1);

namespace BbApp\ContentSource;

/**
 * Base class for content source URL matching callbacks.
 */
abstract class ContentSourceCallbacks
{
	/**
	 * Checks if a URL belongs to this content source.
	 */
    abstract public function url_match_checker(string $url): bool;
}
