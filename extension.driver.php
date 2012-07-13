<?php

	require_once(EXTENSIONS . '/content_negotiation/lib/class.contentnegotiation.php');

	Class Extension_Content_Negotiation extends Extension {

		/**
		 * @var  The ContentNegotiation instance
		 */
		protected $ContentNegotiation = null;

		/**
		 * @var  array  Page types used in the backend to create additional page templates
		 */
		protected $_formats = array();

		/**
		 * @var  array  Headers to be sent with a frontend page
		 */
		protected $_headers = array();


		public function __construct() {
			parent::__construct();

			$content_types = Symphony::Configuration()->get('content_negotiation');

			$this->ContentNegotiation = new ContentNegotiation((array)$content_types);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostValidate',
					'callback' => 'setFormats'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreCreate',
					'callback' => 'createPageFiles'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreEdit',
					'callback' => 'createPageFiles'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPageResolved',
					'callback' => 'setPageTemplate'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPreRenderHeaders',
					'callback' => 'setHeaders'
				)
			);
		}

		public function install() {

			$initial_mappings = $this->ContentNegotiation->get_default_formats();

			foreach($initial_mappings as $type => $content_type){
				Symphony::Configuration()->set($type, $content_type, 'content_negotiation');
			}

			Administration::instance()->saveConfig();
		}

		public function uninstall() {
			Symphony::Configuration()->remove('content_negotiation');
			Administration::instance()->saveConfig();
		}


		public function setFormats(array $context = NULL) {
			// Clean up type list
			$types = preg_split('/\s*,\s*/', $context['fields']['type'], -1, PREG_SPLIT_NO_EMPTY);
			$types = @array_map('trim', $types);

			if (!empty($types)) {
				$this->ContentNegotiation->set_negotiable_formats($types);
				$negotiable_formats = $this->ContentNegotiation->get_negotiable_formats();
				$types = array_keys($negotiable_formats);
				$this->_formats = $types;
			}
		}

		public function createPageFiles(array $context = NULL) {
			$fields = $context['fields'];
			$page_id = $context['page_id'];

			// There are page types defined in the content negotiation formats.
			// So create or rename the template for each format.
			if (!empty($this->_formats)) {
				// New page
				if(empty($page_id)) {
					foreach ($this->_formats as $format) {
						PageManager::createPageFiles(
							$fields['path'], $fields['handle'] . '.' . $format
						);
					}
				}
				// Existing page, potentially rename files
				else {
					$current = PageManager::fetchPageByID($page_id);

					foreach ($this->_formats as $format) {
						$file = PageManager::resolvePageFileLocation($current['path'], $current['handle'] . '.' . $format);

						// A template for the format already exists. Rename it.
						if (file_exists($file)) {
							PageManager::createPageFiles(
								$fields['path'], $fields['handle'] . '.' . $format,
								$current['path'], $current['handle'] . '.' . $format
							);
						}
						// There is no template for this format yet. Create it.
						else {
							PageManager::createPageFiles(
								$fields['path'], $fields['handle'] . '.' . $format
							);
						}
					}
				}
			}
		}

		public function setPageTemplate(array $context = NULL) {
			$page_types = $context['page_data']['type'];

			// Do nothing when no page type is set.
			if (!isset($page_types) || !is_array($page_types) || empty($page_types)) return;

			// Set negotiable content types. Negotiable content types are the union of the page types and formats defined in `config.php.
			$this->ContentNegotiation->set_negotiable_formats($page_types);

			// Negotiate best content type, either directly via file extension or via `Accept` header.
			$this->ContentNegotiation->negotiate_format();
			$negotiable_formats = $this->ContentNegotiation->get_negotiable_formats();
			$requested_format = $this->ContentNegotiation->get_format();

			// If a content type is succesfully negotiated, check that a corresponding template exists and serve it.
			if (!empty($requested_format)) {
				$path_parts = pathinfo($context['page_data']['filelocation']);
				$file = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.' . $requested_format . '.' . $path_parts['extension'];
				if (file_exists($file)) {
					// Let the setHeaders know to set new content type headers
					$this->_headers = array(
						'Content-Type' => $negotiable_formats[$requested_format]
					);
					// Set new template file location
					$context['page_data']['filelocation'] = $file;
				}
			}
		}

		public function setHeaders(array $context = NULL) {
			if (!empty($this->_headers)) {
				foreach ($this->_headers as $name => $value) {
					Frontend::Page()->addHeaderToPage($name, $value);
				}
			}
		}

	}

