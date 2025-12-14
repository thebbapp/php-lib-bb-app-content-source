<?php

declare(strict_types=1);

namespace BbApp\ContentSource;

use UnexpectedValueException;

/**
 * Base class for content source implementations.
 *
 * @var string $id
 * @var array<string, array<string, ?string>> $capabilities
 * @var array<string, string> $entity_types
 * @var ContentSourceCallbacks $callbacks
 */
abstract class ContentSourceAbstract
{
	public static $registry = [];

	public const COLLECTIONS = [
		'section' => 'sections',
		'post' => 'posts',
		'comment' => 'comments'
	];

	public $id;

	protected $capabilities = [
		'section' => ['view' => null, 'post' => null],
		'post' => ['view' => null, 'edit' => null, 'comment' => null],
		'comment' => ['edit' => null]
	];

	protected $entity_types = [
		'section' => null,
		'post' => null,
		'comment' => null
	];

	protected $callbacks;

	/**
	 * Initializes content source with URL matching callbacks.
	 */
	public function __construct(ContentSourceCallbacks $callbacks)
	{
		$this->callbacks = $callbacks;
	}

	/**
	 * Gets entity type mapping or specific entity type by key.
	 */
	public function get_entity_types(?string $entity_type = null)
	{
		if ($entity_type === null) {
			return $this->entity_types;
		}

		if (!isset($this->entity_types[$entity_type])) {
			throw new UnexpectedValueException();
		}

		$value = $this->entity_types[$entity_type];

		if (!is_string($value) || $value === '') {
			throw new UnexpectedValueException();
		}

		return $value;
	}

	/**
	 * Rewrites internal URLs in HTML content to use app-compatible paths.
	 */
	public function rewrite_internal_links(
		string $html,
		string $home_url
	): string {
		if ($html === '') {
			return $html;
		}

		$pattern = '~https?://[^\s<>\"\'()]+~i';

		$callback = function (array $matches) use ($home_url) {
			$resolved = $this->resolve_incoming_url(html_entity_decode($matches[0]));

			if ($resolved === null) {
				return $matches[0];
			}

			$path = $this->get_content_path($resolved['content_type'], (int) $resolved['id']);
			return rtrim($home_url, "/") . $path;
		};

		return (string) preg_replace_callback($pattern, $callback, $html);
	}

	/**
	 * Decodes HTML entities in title for display.
	 */
	public function get_rendered_title(string $title): string
	{
		return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Processes content by rewriting links and decoding HTML entities.
	 */
	public function get_rendered_content(
		string $content,
		string $home_url
	): string {
		if ($content !== '') {
			$content = $this->rewrite_internal_links($content, $home_url);
		}

		return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Parses JSON string into array of unique positive integers.
	 */
	public function parse_json_int_array(?string $json): array
	{
		if (empty($json) || !is_string($json)) {
			return [];
		}

		$decoded = json_decode($json, true);

		if (!is_array($decoded)) {
			return [];
		}

		$ids = [];

		foreach ($decoded as $value) {
			$intVal = (int) $value;

			if ($intVal > 0) {
				$ids[$intVal] = true;
			}
		}

		return array_keys($ids);
	}

	/**
	 * Builds SQL IN clause placeholders for prepared statements.
	 */
	public function build_in_placeholders(array $ids): string
	{
		return implode(',', array_fill(0, count($ids), '%d'));
	}

	/**
	 * Builds app URL path for content item by type and ID.
	 */
	public function get_content_path(string $content_type, int $post_id): string
	{
		$collection = static::COLLECTIONS[$content_type] ?? null;

		if ($collection === null) {
			throw new UnexpectedValueException();
		}

		return "/{$collection}/{$post_id}";
	}

	/**
	 * Checks if user has permission for specific action on content.
	 */
	abstract public function user_can(
		int $user_id,
		string $intent,
		string $content_type,
		int $content_id
	): bool;

	/**
	 * Checks if current user has permission for specific action on content.
	 */
	abstract public function current_user_can(
		string $intent,
		string $content_type,
		int $content_id
	): bool;

	/**
	 * Retrieves content object by type and ID.
	 */
	abstract public function get_content(string $content_type, int $id);

	/**
	 * Determines content type from content object.
	 */
	abstract public function get_content_type($object): string;

	/**
	 * Gets root section ID for content hierarchy.
	 */
	abstract public function get_root_section_id(): int;

	/**
	 * Gets parent ID of root section.
	 */
	abstract public function get_root_parent_id(): int;

	/**
	 * Gets permalink URL for content by type and ID.
	 */
	abstract public function get_link(string $content_type, int $id): string;

	/**
	 * Resolves URL to content type and ID array.
	 */
	abstract public function resolve_incoming_url(string $url): ?array;

	/**
	 * Gets content source options data for API response.
	 */
	abstract public function get_options_data(): array;

	/**
	 * Gets content source features data for API response.
	 */
	abstract public function get_features_data(): array;

	/**
	 * Registers API hooks.
	 */
	abstract public function register(): void;
}
