<?php

class protector_postcommon_post_htmlpurify4everyone extends ProtectorFilterAbstract {

	function execute() {
		$_POST = $this->purify_recursive($_POST);
	}

	function purify_recursive($data) {
		if (is_array($data)) {
			return array_map(array (
				$this,
				'purify_recursive'
			), $data);
		}

		return strlen((string) $data) > 32 ? \Icms\Core\HTMLFilter::filterHTML((string) $data) : $data;
	}
}
