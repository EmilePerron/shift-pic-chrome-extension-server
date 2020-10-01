<?php

namespace App\Lib;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

abstract class Cache
{
	public static function magicGet($suffixes = [], $default = null)
    {
		$key = static::magicKey($suffixes);
		return static::get($key, $default);
	}

	public static function magicSet($value, $expiresAfter = 0, $suffixes = [])
    {
		$key = static::magicKey($suffixes);
		return static::set($key, $value, $expiresAfter);
	}

	public static function magicKey($suffixes = [])
    {
		$origin = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
		$suffixes = $suffixes ?: $origin['args'];
		$suffix = implode('.', $suffixes);
		$key = $origin['class'] . '.' . $origin['function'] . ($suffix ? '.' . $suffix : '');
		$key = strtolower(str_replace('\\', '.', $key));

		return $key;
	}

	public static function get($key, $default = null)
    {
		$cache = static::getCache($key);

		if ($cache->isHit()) {
			return $cache->get();
		}

		return $default;
	}

	public static function set($key, $value, $expiresAfter = 0)
    {
		$cache = static::getCache($key);
		$cache->set($value);

		if ($expiresAfter) {
			$cache->expiresAfter($expiresAfter);
		}

		static::getFilesystemCache()->save($cache);

		return $value;
	}

	public static function remove($key)
	{
		$key = static::getCacheKey($key);
		return static::getFilesystemCache()->delete($key);
	}

	public static function prune()
	{
		return static::getFilesystemCache()->prune();
	}

	protected static function getCacheKey($key)
	{
		# Standarize the key by removing reserved characters
		return str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], 'RESERVED', $key);
	}

	protected static function getCache($key = null)
    {
		$key = static::getCacheKey($key);
		return static::getFilesystemCache()->getItem($key);
	}

	protected static function getFilesystemCache()
    {
		static $filesystemCache = null;

		if (!$filesystemCache) {
			$filesystemCache = new FilesystemAdapter();
		}

		return $filesystemCache;
	}
}
