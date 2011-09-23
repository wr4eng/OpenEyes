<?php
/*
_____________________________________________________________________________
(C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
(C) OpenEyes Foundation, 2011
This file is part of OpenEyes.
OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
_____________________________________________________________________________
http://www.openeyes.org.uk   info@openeyes.org.uk
--
*/

class ExampleSummaryTest extends CDbTestCase
{
	protected $widget;

	public $fixtures = array(
		'episodes' => 'Episode',
		'events' => 'Event',
	);

	protected function setUp()
	{
		$this->widget = new ExampleSummary('ExampleSummary');
		parent::setUp();
	}

	public function testRun_NoEpisodeId_ThrowsException()
	{
		$this->setExpectedException('CHttpException', 'No episode id provided.');
		$this->widget->run();
	}

	public function testRun_InvalidEpisodeId_ThrowsException()
	{
		$this->widget->episode_id = 99999999;

		$this->setExpectedException('CHttpException', 'There is no episode of that id.');
		$this->widget->run();
	}

	public function testRun_RendersSummaryView()
	{
		$episode = $this->episodes('episode1');

		$mockWidget = $this->getMock('ExampleSummary', array('render'),
			array('ExampleSummary'));

		$mockWidget->episode_id = $episode->id;

		$mockWidget->expects($this->any())
			->method('render')
			->with('ExampleSummary');

		$mockWidget->run();
	}
}
