<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;


use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\AssignedUsers;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FileServiceTest extends TestCase {

	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IAppData|MockObject */
	private $appData;
	/** @var IRequest|MockObject */
	private $request;
	/** @var ILogger|MockObject */
	private $logger;
	/** @var FileService */
	private $fileService;

	public function setUp() {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->fileService = new FileService($this->l10n, $this->appData, $this->request, $this->logger);
    }

    public function mockGetFolder($cardId) {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('file-card-' . $cardId)
			->willReturn($folder);
		return $folder;
	}
	public function mockGetFolderFailure($cardId) {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('file-card-' . $cardId)
			->will($this->throwException(new \OCP\Files\NotFoundException()));
		$this->appData->expects($this->once())
			->method('newFolder')
			->with('file-card-' . $cardId)
			->willReturn($folder);
		return $folder;
	}

	private function getAttachment() {
		$attachment = new Attachment();
		$attachment->setId(1);
		$attachment->setCardId(123);
		return $attachment;
	}

	private function mockGetUploadedFileEmpty() {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn([]);
	}
	private function mockGetUploadedFileError($error) {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn(['error' => $error]);
	}
	private function mockGetUploadedFile() {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn([
				'name' => 'file.jpg',
				'tmp_name' => __FILE__,
			]);
	}

	public function testExtendDataNotFound() {
		$attachment = $this->getAttachment();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->will($this->throwException(new \OCP\Files\NotFoundException()));
		$this->assertEquals($attachment, $this->fileService->extendData($attachment));
	}

	public function testExtendDataNotPermitted() {
		$attachment = $this->getAttachment();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->will($this->throwException(new \OCP\Files\NotPermittedException()));
		$this->assertEquals($attachment, $this->fileService->extendData($attachment));
	}

	public function testExtendData() {
		$attachment = $this->getAttachment();
		$expected = $this->getAttachment();
		$expected->setExtendedData([
			'filesize' => 100,
			'mimetype' => 'image/jpeg',
			'info' => pathinfo(__FILE__)
		]);

		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())->method('getSize')->willReturn(100);
		$file->expects($this->once())->method('getMimeType')->willReturn('image/jpeg');
		$file->expects($this->once())->method('getName')->willReturn(__FILE__);

		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->willReturn($file);
		$this->assertEquals($expected, $this->fileService->extendData($attachment));
	}

	/**
	 * @expectedException \Exception
	 */
	public function testCreateEmpty() {
		$attachment = $this->getAttachment();
		$this->l10n->expects($this->any())
			->method('t')
			->willReturn('Error');
		$this->mockGetUploadedFileEmpty();
		$this->fileService->create($attachment);
	}

	/**
	 * @expectedException \Exception
	 */
	public function testCreateError() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFileError(UPLOAD_ERR_INI_SIZE);
		$this->l10n->expects($this->any())
			->method('t')
			->willReturn('Error');
		$this->fileService->create($attachment);
	}

	public function testCreate() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(false);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('newFile')
			->willReturn($file);

		$this->fileService->create($attachment);
	}

	public function testCreateNoFolder() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolderFailure(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(false);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('newFile')
			->willReturn($file);

		$this->fileService->create($attachment);
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage File already exists.
	 */
	public function testCreateExists() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(true);
		$this->fileService->create($attachment);
	}

	public function testUpdate() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);

		$this->fileService->update($attachment);
	}

	public function testDelete() {
		$attachment = $this->getAttachment();
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->mockGetFolder('123');
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);
		$file->expects($this->once())
			->method('delete');
		$this->fileService->delete($attachment);
	}

	public function testDisplay() {
		$attachment = $this->getAttachment();
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->mockGetFolder('123');
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);
		$file->expects($this->exactly(2))
			->method('getMimeType')
			->willReturn('image/jpeg');
		$actual = $this->fileService->display($attachment);
		$expected = new FileDisplayResponse($file);
		$expected->addHeader('Content-Type', 'image/jpeg');
		$this->assertEquals($expected, $actual);
	}

	public function testDisplayPdf() {
		$attachment = $this->getAttachment();
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->mockGetFolder('123');
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);
		$file->expects($this->exactly(2))
			->method('getMimeType')
			->willReturn('application/pdf');
		$actual = $this->fileService->display($attachment);
		$expected = new FileDisplayResponse($file);
		$expected->addHeader('Content-Type', 'application/pdf');
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedObjectDomain('\'self\'');
		$policy->addAllowedObjectDomain('blob:');
		$expected->setContentSecurityPolicy($policy);
		$this->assertEquals($expected, $actual);
	}

	public function testAllowUndo() {
		$this->assertTrue($this->fileService->allowUndo());
	}

	public function testMarkAsDeleted() {
		// TODO: use proper ITimeFactory in the service so we can mock the call to time
		$attachment = $this->getAttachment();
		$this->assertEquals(0, $attachment->getDeletedAt());
		$this->fileService->markAsDeleted($attachment);
		$this->assertGreaterThan(0, $attachment->getDeletedAt());
	}
}