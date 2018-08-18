<?php
namespace wcf\system\event\listener;
use wcf\data\attachment\Attachment;
use wcf\data\attachment\AttachmentAction;
use wcf\data\attachment\AttachmentEditor;
use wcf\system\exception\SystemException;
use wcf\system\image\ImageHandler;
use wcf\util\FileUtil;

/**
 * Scales an attachment to a preferred size. 
 * 
 * @author	Joshua Ruesweg
 * @copyright	2016-2018 WCFLabs.de
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Event\Listener
 */
class ScaleAttachmentListener implements IParameterizedEventListener {
	/**
	 * @inheritDoc
	 * 
	 * @param       AttachmentAction        $eventObj
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if ($eventObj->getActionName() != 'upload') return;
		
		$result = $eventObj->getReturnValues();
		
		if (!isset($result['returnValues']['attachments'])) return; 
		
		foreach ($result['returnValues']['attachments'] as $attachmentID => $attachmentData) {
			$this->rebuild(new Attachment($attachmentData['attachmentID']));
		}
	}
	
	/**
	 * Rebuilds an attachment an resize it to a max size. 
	 * 
	 * @param       Attachment      $attachment
	 */
	private function rebuild(Attachment $attachment) {
		if ($attachment->isImage && FileUtil::checkMemoryLimit($attachment->width * $attachment->height * ($attachment->fileType == 'image/png' ? 4 : 3) * 2.1)) {
			try {
				if ($attachment->height > SCALE_ATTACHMENTS_MAX_SIZE || $attachment->width > SCALE_ATTACHMENTS_MAX_SIZE) {
					$imageAdapter = ImageHandler::getInstance()->getAdapter();
					$imageAdapter->loadFile($attachment->getLocation());
					
					$ratio = ($attachment->height > $attachment->width ? $attachment->height : $attachment->width) / SCALE_ATTACHMENTS_MAX_SIZE;
					$resizeHeight = $attachment->height / $ratio;
					$resizeWidth = $attachment->width / $ratio;
					
					$imageAdapter->resize(0, 0, $attachment->width, $attachment->height, $resizeWidth, $resizeHeight);
					
					$data = [
						'height' => $resizeHeight,
						'width' => $resizeWidth
					];
					
					$imageAdapter->writeImage($attachment->getLocation());
					
					$data['filesize'] = @filesize($attachment->getLocation());
					
					$editor = new AttachmentEditor($attachment);
					$editor->update($data);
				}
			}
			catch (SystemException $e) {
				// log exception
				$e->getExceptionID();
			}
		}
	}
}
