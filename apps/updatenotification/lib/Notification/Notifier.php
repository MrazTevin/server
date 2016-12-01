<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UpdateNotification\Notification;


use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $notificationManager;

	/** @var IFactory */
	protected $l10NFactory;

	/** @var string[] */
	protected $appVersions;

	/**
	 * Notifier constructor.
	 *
	 * @param IURLGenerator $url
	 * @param IManager $notificationManager
	 * @param IFactory $l10NFactory
	 */
	public function __construct(IURLGenerator $url, IManager $notificationManager, IFactory $l10NFactory) {
		$this->url = $url;
		$this->notificationManager = $notificationManager;
		$this->l10NFactory = $l10NFactory;
		$this->appVersions = $this->getAppVersions();
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, $languageCode) {
		if ($notification->getApp() !== 'updatenotification') {
			throw new \InvalidArgumentException();
		}

		$l = $this->l10NFactory->get('updatenotification', $languageCode);
		if ($notification->getObjectType() === 'core') {
			$this->updateAlreadyInstalledCheck($notification, $this->getCoreVersions());

			$parameters = $notification->getSubjectParameters();
			$notification->setParsedSubject($l->t('Update to %1$s is available.', [$parameters['version']]));
		} else {
			$appInfo = $this->getAppInfo($notification->getObjectType());
			$appName = ($appInfo === null) ? $notification->getObjectType() : $appInfo['name'];

			if (isset($this->appVersions[$notification->getObjectType()])) {
				$this->updateAlreadyInstalledCheck($notification, $this->appVersions[$notification->getObjectType()]);
			}

			$notification->setParsedSubject($l->t('Update for %1$s to version %2$s is available.', [$appName, $notification->getObjectId()]))
				->setRichSubject($l->t('Update for {app} to version %s is available.', $notification->getObjectId()), [
					'app' => [
						'type' => 'app',
						'id' => $notification->getObjectType(),
						'name' => $appName,
					]
				]);
		}

		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('updatenotification', 'notification.svg')));

		return $notification;
	}

	/**
	 * Remove the notification and prevent rendering, when the update is installed
	 *
	 * @param INotification $notification
	 * @param string $installedVersion
	 * @throws \InvalidArgumentException When the update is already installed
	 */
	protected function updateAlreadyInstalledCheck(INotification $notification, $installedVersion) {
		if (version_compare($notification->getObjectId(), $installedVersion, '<=')) {
			$this->notificationManager->markProcessed($notification);
			throw new \InvalidArgumentException();
		}
	}

	protected function getCoreVersions() {
		return implode('.', \OCP\Util::getVersion());
	}

	protected function getAppVersions() {
		return \OC_App::getAppVersions();
	}

	protected function getAppInfo($appId) {
		return \OC_App::getAppInfo($appId);
	}
}
