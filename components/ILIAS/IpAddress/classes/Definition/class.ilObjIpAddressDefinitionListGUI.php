<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

final class ilObjIpAddressDefinitionListGUI extends ilObjectListGUI
{
    /**
     * initialisation
     */
    public function init(): void
    {
        parent::init();

        $this->delete_enabled = true;
        $this->cut_enabled = false;
        $this->info_screen_enabled = true;
        $this->copy_enabled = false;

        $this->type = "ipad";
        $this->gui_class_name = strtolower(ilObjIpAddressDefinitionGUI::class);
        $this->commands = ilObjIpAddressDefinitionAccess::_getCommands();
    }

    /**
     * @param string $cmd
     * @return string
     * @throws ilCtrlException
     */
    public function getCommandLink(string $cmd): string
    {
        $this->ctrl->setParameterByClass($this->gui_class_name, 'ref_id', $this->ref_id);
        $link = $this->ctrl->getLinkTargetByClass($this->gui_class_name, $cmd);
        $this->ctrl->clearParameterByClass($this->gui_class_name, 'ref_id');
        return $link;
    }

    public function insertDeleteCommand(): void {
        $is_referenced = (new ilObjIpAddressDefinition($this->ref_id))->isReferenced();
        if (!$is_referenced) parent::insertDeleteCommand();
    }

    public function insertTimingsCommand(): void {
        return;
    }

    public function insertCommonSocialCommands(bool $header_actions = false): void {
        return;
    }
}
