<?php
/***************************************************************************
 *   Copyright (C) 2006-2007 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 3 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup Builders
	**/
	abstract class BaseBuilder extends StaticFactory
	{
		public static function build(MetaClass $class)
		{
			throw new UnimplementedFeatureException('i am forgotten method');
		}
		
		protected static function buildFillers(MetaClass $class)
		{
			$out = null;
			
			$className = $class->getName();
			$varName = strtolower($className[0]).substr($className, 1);

			$setters = array();
			
			$standaloneFillers = array();
			$chainFillers = array();
			
			if ($class->getParent())
				$setterIndent = 4;
			else
				$setterIndent = 5;
			
			foreach ($class->getProperties() as $property) {
				
				$filler = $property->toDaoSetter($className);
				
				if ($filler !== null) {
					
					$setters[] = $property->toDaoField($className, $setterIndent);
					
					if (
						(
							!$property->getType()->isGeneric()
							|| $property->getType() instanceof ObjectType
						)
						&& !$property->isRequired()
						&& !$property->getType() instanceof RangeType
					)
						$standaloneFillers[] =
							implode(
								"\n",
								explode("\n", $filler)
							);
					else
						$chainFillers[] =
							implode(
								"\n",
								explode("\n", $filler)
							);
				}
			}
			
			$out .= implode("->\n", $setters).";\n";

			$out .= <<<EOT
		}

EOT;

			if (
				$class->getPattern() instanceof StraightMappingPattern
				|| $class->getPattern() instanceof DictionaryClassPattern
			) {
				$out .= <<<EOT

/**
 * @return {$className}
**/
public function makeObject(&\$array, \$prefix = null)
{
	return \$this->fillObject(new {$className}(), \$array, \$prefix);
}

EOT;
			} else {
				$out .= <<<EOT
				
// there is no makeObject because of abstract nature of meta-class
				
EOT;
			}
			
			$out .= <<<EOT

/**
 * @return {$className}
**/
protected function fillObject(/* {$className} */ \${$varName}, &\$array, \$prefix = null)
{

EOT;
			if ($class->getParent()) {
				$out .= <<<EOT
parent::fillObject(\${$varName}, \$array, \$prefix);


EOT;
			}
			
			if ($chainFillers) {
				
				$out .= "\${$varName}->\n";
				
				$out .= implode("->\n", $chainFillers).";\n\n";
			}
			
			if ($standaloneFillers) {
				$out .= implode("\n", $standaloneFillers)."\n";
			}

			$out .= <<<EOT
			return \${$varName};
		}
	}

EOT;
			return $out;
		}
		
		protected static function buildPointers(MetaClass $class)
		{
			$out = null;
			
			if ($source = $class->getSourceLink()) {
				$out = <<<EOT
public function getLinkName()
{
	return '{$source}';
}


EOT;
			}
				
			if (!$class->getPattern() instanceof AbstractClassPattern) {
				$out .= <<<EOT
public function getTable()
{
	return '{$class->getDumbName()}';
}

public function getObjectName()
{
	return '{$class->getName()}';
}

public function getSequence()
{
	return '{$class->getDumbName()}_id';
}
EOT;
			} else {
				$out .= <<<EOT
// no get{Table,ObjectName,Sequence} for abstract class
EOT;
			}
			
			return $out;
		}
		
		protected static function buildMapping(MetaClass $class)
		{
			$mapping = array();
			
			foreach ($class->getProperties() as $property) {
				
				$row = null;
				
				if ($property->getType()->isGeneric()) {
					
					$name = $property->getName();
					$dumbName = $property->getDumbName();
					
					if ($property->getType() instanceof RangeType) {
						
						$row =
							array(
								"'{$name}Min' => '{$dumbName}_min'",
								"'{$name}Max' => '{$dumbName}_max'"
							);
						
					} else {
						if ($name == $dumbName)
							$map = 'null';
						else
							$map = "'{$dumbName}'";
						
						$row .= "'{$name}' => {$map}";
					}
				} else {
					
					$relation = $property->getRelation();
					
					if (
						$relation->getId() == MetaRelation::ONE_TO_ONE
					) {
						$remoteClass =
							MetaConfiguration::me()->
							getClassByName(
								$property->getType()->getClass()
							);
						
						$identifier = $remoteClass->getIdentifier();
						
						$row .=
							"'{$property->getName()}".ucfirst($identifier->getName())
							."' => '{$property->getDumbIdName()}'";
					} else
						$row = null;
				}
				
				if ($row) {
					if (is_array($row))
						$mapping = array_merge($mapping, $row);
					else // string
						$mapping[] = $row;
				}
			}
			
			return $mapping;
		}
		
		protected static function getHead()
		{
			$head = self::startCap();
			
			$head .=
				' *   This file is autogenerated - do not edit.'
				.'                               *';

			return $head."\n".self::endCap();
		}
		
		protected static function startCap()
		{
			$version = ONPHP_VERSION;
			$date = date('Y-m-d H:i:s');
			
			$info = " *   Generated by onPHP-{$version} at {$date}";
			$info = str_pad($info, 77, ' ', STR_PAD_RIGHT).'*';
			
			$cap = <<<EOT
<?php
/*****************************************************************************
 *   Copyright (C) 2006-2007, onPHP's MetaConfiguration Builder.             *
{$info}

EOT;

			return $cap;
		}
		
		protected static function endCap()
		{
			$cap = <<<EOT
 *****************************************************************************/
/* \$Id\$ */


EOT;
			return $cap;
		}
		
		protected static function getHeel()
		{
			return '?>';
		}
	}
?>