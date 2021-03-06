<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

use ContaoCommunityAlliance\Contao\RootRelations\ControllerProxy;

/**
 * @author Oliver Hoff
 */
class GroupDCA {

	public function keySelectriAJAXCallback($dc) {
		$key = 'isAjaxRequest';

		// the X-Requested-With gets deleted on ajax requests by selectri widget,
		// to enable regular contao DC process, but we need this behavior for the
		// editAll call respecting the passed id
		$$key = EnvironmentProxy::getCacheValue($key);
		EnvironmentProxy::setCacheValue($key, true);

		$return = $dc->editAll(\Input::getInstance()->get('cca_lr_id'));

		// this would never be reached, but we clean up the env
		EnvironmentProxy::setCacheValue($key, $$key);

		return $return;
	}

	public function keyEditRelations() {
		$fields = array('cca_lr_pageInfo', 'cca_lr_relations');
		$roots = array_unique(array_map('intval', array_filter((array) $_GET['roots'], function($root) { return $root >= 1; })));

		switch($_GET['filter']) {
			case 'incomplete':
				$ids = LanguageRelations::getIncompleteRelatedPages($roots[0]);
				$ids || $msg = $GLOBALS['TL_LANG']['tl_cca_lr_group']['noIncompleteRelations'];
				break;

			case 'ambiguous':
				$ids = LanguageRelations::getAmbiguousRelatedPages($roots[0]);
				$ids || $msg = $GLOBALS['TL_LANG']['tl_cca_lr_group']['noAmbiguousRelations'];
				break;

			default:
				if($roots) {
					$wildcards = rtrim(str_repeat('?,', count('roots')), ',');
					$sql = "SELECT id FROM tl_page WHERE cca_rr_root IN ($wildcards) AND type != 'root'";
					$result = \Database::getInstance()->prepare($sql)->executeUncached($roots);
					$ids = $result->fetchEach('id');
				}
				break;
		}

		if(!$ids) {
			ControllerProxy::addConfirmationMessage($msg ?: $GLOBALS['TL_LANG']['tl_cca_lr_group']['noPagesToEdit']);
			ControllerProxy::redirect(ControllerProxy::getReferer());
			return;
		}

		$session = \Session::getInstance()->getData();
		$session['CURRENT']['IDS'] = $ids;
		$session['CURRENT']['tl_page'] = $fields;
		\Session::getInstance()->setData($session);

		ControllerProxy::redirect('contao/main.php?do=cca_lr_group&table=tl_page&act=editAll&fields=1&rt=' . REQUEST_TOKEN);
	}

	public function groupGroup($group, $mode, $field, $row, $dc) {
		return $row['title'];
	}

	public function labelGroup($row, $label) {
		$sql = 'SELECT * FROM tl_page WHERE cca_lr_group = ? ORDER BY title';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($row['id']);

		$groupRoots = array();
		while($result->next()) {
			$groupRoots[] = $result->row();
		}

		$tpl = new \BackendTemplate('cca_lr_groupRoots');
		$tpl->groupRoots = $groupRoots;

		return $tpl->parse();
	}

	public function getRootsOptions() {
		$sql = <<<SQL
SELECT		page.id,
			page.title,
			page.language,
			grp.id				AS grpID,
			grp.title			AS grpTitle

FROM		tl_page				AS page
LEFT JOIN	tl_cca_lr_group		AS grp			ON grp.id = page.cca_lr_group

WHERE		page.type = ?

ORDER BY	grp.title IS NOT NULL,
			grp.title,
			page.title
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached('root');

		$options = array();
		while($result->next()) {
			$groupTitle = $result->grpID
				? $result->grpTitle . ' (ID ' . $result->grpID . ')'
				: $GLOBALS['TL_LANG']['tl_cca_lr_group']['notGrouped'];
			$options[$groupTitle][$result->id] = $result->title . ' [' . $result->language . ']';
		}

		return $options;
	}

	private $roots = array();

	public function onsubmitGroup($dc) {
		if(isset($this->roots[$dc->id])) {
			$sql = 'UPDATE tl_page SET cca_lr_group = NULL WHERE cca_lr_group = ?';
			\Database::getInstance()->prepare($sql)->executeUncached($dc->id);

			$roots = deserialize($this->roots[$dc->id], true);
			if($roots) {
				$wildcards = rtrim(str_repeat('?,', count($roots)), ',');
				$sql = 'UPDATE tl_page SET cca_lr_group = ? WHERE id IN (' . $wildcards . ')';
				array_unshift($roots, $dc->id);
				\Database::getInstance()->prepare($sql)->executeUncached($roots);
			}
		}
	}

	public function loadRoots($value, $dc) {
		$sql = 'SELECT id FROM tl_page WHERE cca_lr_group = ? AND type = ? ORDER BY title';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id, 'root');
		return $result->fetchEach('id');
	}

	public function saveRoots($value, $dc) {
		$this->roots[$dc->id] = $value;
		return null;
	}

}
