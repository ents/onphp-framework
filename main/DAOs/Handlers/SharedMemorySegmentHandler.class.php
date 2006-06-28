<?php
/***************************************************************************
 *   Copyright (C) 2006 by Konstantin V. Arkhipov                          *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup DAOs
	**/
	final class SharedMemorySegmentHandler implements SegmentHandler
	{
		const SEGMENT_SIZE = 2097152; // 2 ^ 21
		
		private $id = null;
		
		public function __construct($segmentId)
		{
			$this->id = $segmentId;
			
			return $this;
		}
		
		public function touch($key)
		{
			try {
				$shm = shm_attach($this->id, self::SEGMENT_SIZE, 0600);
			} catch (BaseException $e) {
				return false;
			}

			try {
				$result = shm_put_var($shm, $key, true);
				shm_detach($shm);
			} catch (BaseException $e) {
				// not enough shared memory left, rotate it.
				shm_detach($shm);
				return $this->uncacheLists();
			}
			
			return $result;
		}
		
		public function unlink($key)
		{
			try {
				$shm = shm_attach($this->id, self::SEGMENT_SIZE, 0600);
			} catch (BaseException $e) {
				return false;
			}
			
			try {
				$result = shm_remove_var($shm, $key);
				shm_detach($shm);
				return $result;
			} catch (BaseException $e) {
				// non existent key
				shm_detach($shm);
				return false;
			}
			
			/* NOTREACHED */
		}
		
		public function ping($key)
		{
			try {
				$shm = shm_attach($this->id, self::SEGMENT_SIZE, 0600);
			} catch (BaseException $e) {
				return false;
			}
			
			try {
				$result = shm_get_var($shm, $key);
			} catch (BaseException $e) {
				// variable key N doesn't exist, bleh
				shm_detach($shm);
				return false;
			}
			
			shm_detach($shm);
			
			return $result;
		}
		
		public function drop()
		{
			try {
				$shm = shm_attach($this->id, self::SEGMENT_SIZE, 0600);
			} catch (BaseException $e) {
				return false;
			}
			
			$result = shm_remove($shm);
			
			shm_detach($shm);
			
			return $result;
		}
	}
?>