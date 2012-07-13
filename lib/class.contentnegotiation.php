<?php

	/**
	 * Content Negotiation for Symphony CMS
	 */
	class ContentNegotiation {

		/**
		 * @var  string  The detected response format
		 */
		protected $_format = null;

		/**
		 * @var  array  Supported (global) formats and their content type
		 */
		protected $_supported_formats = array();

		/**
		 * @var  array  Negotiable formats of the current page
		 */
		protected $_negotiable_formats = array();

		/**
		 * @var  array  Default formats (for installation) and their content type
		 */
		protected $_default_formats = array(
			'html' => 'text/html',
			'xml' => 'application/xml',
			'json' => 'application/json',
			'jsonp'=> 'text/javascript',
			'csv' => 'application/csv',
			'php' => 'text/plain'
		);

		function __construct(array $formats = array()) {
			$this->_supported_formats = $formats;
		}

		public function get_default_formats() {
			return $this->_default_formats;
		}

		public function get_supported_formats() {
			return $this->_supported_formats;
		}

		public function get_negotiable_formats() {
			return $this->_negotiable_formats;
		}

		public function get_format() {
			return $this->_format;
		}

		/**
		 * Set negotiable formats
		 *
		 * @param array $formats
		 *	Array of formats (keys of supported formats)
		 */
		public function set_negotiable_formats(array $formats = array()) {
			$this->_negotiable_formats = array_intersect_key($this->_supported_formats, array_flip($formats));
		}

		/**
		 * Negotiate format
		 *
		 * Detect which format should be used to output the data
		 * Taken from https://github.com/fuel/core/blob/1.3/develop/classes/controller/rest.php
		 *
		 * @return  string
		 */
		public function negotiate_format() {
			// A format has been passed as an argument in the URL and it is supported
			if (!empty($_REQUEST['content-type']) && in_array($_REQUEST['content-type'], array_flip($this->_negotiable_formats))) {
				$this->_format = General::sanitize($_REQUEST['content-type']);
				return General::sanitize($_REQUEST['content-type']);
			}

			// Otherwise, check the HTTP_ACCEPT
			if (isset($_SERVER['HTTP_ACCEPT'])) {

				// Split the Accept header and build an array of quality scores for each format
				$fragments = new CachingIterator(new ArrayIterator(preg_split('/[,;]/', $_SERVER['HTTP_ACCEPT'])));
				$acceptable = array();
				$next_is_quality = false;
				foreach ($fragments as $fragment) {
					$quality = 1;
					// Skip the fragment if it is a quality score
					if ($next_is_quality) {
						$next_is_quality = false;
						continue;
					}

					// If next fragment exists and is a quality score, set the quality score
					elseif ($fragments->hasNext()) {
						$next = $fragments->getInnerIterator()->current();
						if (strpos($next, 'q=') === 0) {
							list($key, $quality) = explode('=', $next);
							$next_is_quality = true;
						}
					}

					$acceptable[$fragment] = $quality;
				}

				// Sort the formats by score in descending order
				uasort($acceptable, function($a, $b) {
					$a = (float) $a;
					$b = (float) $b;
					return ($a > $b) ? -1 : 1;
				});

				// Check each of the acceptable formats against the supported formats
				foreach ($acceptable as $pattern => $quality) {
					// The Accept header can contain wildcards in the format
					$find = array('*', '/');
					$replace = array('.*', '\/');
					$pattern = '/^' . str_replace($find, $replace, $pattern) . '$/';
					foreach ($this->_negotiable_formats as $format => $mime) {
						if (preg_match($pattern, $mime)) {
							$this->_format = $format;
							return $format;
						}
					}
				}
			}
		}

	}
