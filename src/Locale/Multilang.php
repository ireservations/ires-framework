<?php

namespace Framework\Locale;

abstract class Multilang {

	public $language_id;
	public $translations = [];

	public function setLanguage( $language, $force = false ) {
		if ( $language ) {
			$this->ensureTranslationsForLanguage($language, $force);
			$this->language_id = $language;
		}
	}

	public function translate( $key, array $options = [] ) {
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

	public function ucfirst( $translation ) {
		return self::mbUcfirst($translation);
	}

	public function ensureTranslationsForLanguage( $language, $force = false ) {
		if ( $force || !isset($this->translations[$language]) ) {
			if ( !($this->translations[$language] = $this->getTranslations($language)) ) {
				return false;
			}
		}

		return true;
	}

	abstract public function getTranslations( $language );

	static public function mbUcfirst( $string ) {
		$enc = mb_internal_encoding();
		return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) . mb_substr($string, 1, mb_strlen($string, $enc), $enc);
	}

}
