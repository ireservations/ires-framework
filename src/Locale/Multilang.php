<?php

namespace Framework\Locale;

/**
 * @template TLangId of int|string
 */
abstract class Multilang {

	/** @var TLangId */
	public int|string $language_id;
	/** @var array<TLangId, array<string, string>> */
	public array $translations = [];

	/**
	 * @param TLangId $language
	 */
	public function setLanguage( $language, bool $force = false ) : void {
		if ( $language ) {
			$this->ensureTranslationsForLanguage($language, $force);
			$this->language_id = $language;
		}
	}

	/**
	 * @param AssocArray $options
	 */
	public function translate( string $key, array $options = [] ) : string {
		if ( !$key ) return '';

		$ucfirst = $options['ucfirst'] ?? true;
		$language = $options['language'] ?? $this->language_id;

		if ( !$this->ensureTranslationsForLanguage($language) ) {
			return $key;
		}

		$translations = $this->translations[$language];

		$lkey = strtoupper($key);
		if ( !isset($translations[$lkey]) ) {
			return $key;
		}

		$translation = trim($translations[strtoupper($key)]);
		if ( $ucfirst ) {
			$translation = $this->ucfirst($translation);
		}

		return $translation;
	}

	public function ucfirst( string $translation ) : string {
		return self::mbUcfirst($translation);
	}

	/**
	 * @param TLangId $language
	 */
	public function ensureTranslationsForLanguage( $language, bool $force = false ) : bool {
		if ( $force || !isset($this->translations[$language]) ) {
			if ( !($this->translations[$language] = $this->getTranslations($language)) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param TLangId $language
	 * @return array<string, string>
	 */
	abstract public function getTranslations( $language ) : array;

	static public function mbUcfirst( string $string ) : string {
		$enc = mb_internal_encoding();
		return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) . mb_substr($string, 1, mb_strlen($string, $enc), $enc);
	}

}
