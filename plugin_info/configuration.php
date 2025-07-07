<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<div class="row">
    <div class="col-lg-6">
        <form class="form-horizontal">
            <fieldset>
                <legend><i class="fas fa-server"></i> {{Paramètres de connexion Niko Home Control}}</legend>
                <div class="form-group">
                    <label class="col-md-4 control-label">{{Adresse IP de la passerelle}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Indiquez l'adresse IP locale de votre contrôleur ou hub Niko Home Control.}}"></i></sup>
                    </label>
                    <div class="col-md-6">
                        <input class="configKey form-control" data-l1key="niko_ip" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-4 control-label">{{Jeton d'authentification (JWT)}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Copiez ici le jeton JWT obtenu depuis le logiciel de programmation Niko. Il est très long.}}"></i></sup>
                    </label>
                    <div class="col-md-6">
                        <input type="password" class="configKey form-control" data-l1key="niko_jwt" />
                    </div>
                </div>
            </fieldset>
        </form>
    </div>
</div>
