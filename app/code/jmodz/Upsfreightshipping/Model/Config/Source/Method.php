<?php
namespace jmodz\Upsfreightshipping\Model\Config\Source;

class Method implements \Magento\Framework\Option\ArrayInterface
{
 
public function getCode($type, $code = '') {
		$codes = array (
            'method'=>array(
                'STANDARD' => 'Standard Service (commercial delivery)',
		),
			'freight_class' => array (
				'50' => '50',
				'55' => '55',
				'60' => '60',
				'65' => '65',
				'70' => '70',
				'77.5' => '77.5',
				'85' => '85',
				'92.5' => '92.5',
				'100' => '100',
				'110' => '110',
				'125' => '125',
				'150' => '150',
				'175' => '175',
				'200' => '200',
				'250' => '250',
				'300' => '300',
				'400' => '400',
				'500' => '500',
		),
		'package_type' => array (
				'BAG' => 'Bag',
				'BAL' => 'Bale',
				'BAR' => 'Barrel',
				'BDL' => 'Bundle',
				'BIN' => 'Bin',
				'BOX' => 'Box',
				'BSK' => 'Basket',
				'BUN' => 'Bunch',
				'CAB' => 'Cabinet',
				'CAN' => 'Can',
				'CAR' => 'Carrier',
				'CAS' => 'Case',
				'CBY' => 'Carboy',
				'CON' => 'Container',
				'CRT' => 'Crate',
				'CSK' => 'Cask',
				'CTN' => 'Carton',
				'CYL' => 'Cylinder',
				'DRM' => 'Drum',
				'LOO' => 'Loose',
				'OTH' => 'Other',
				'PAL' => 'Pail',
				'PCS' => 'Pieces',
				'PKG' => 'Package',
				'PLN' => 'Pipe Line',
				'PLT' => 'Pallet',
				'RCK' => 'Rack',
				'REL' => 'Reel',
				'ROL' => 'Roll',
				'SKD' => 'Skid',
				'SPL' => 'Spool',
				'TBE' => 'Tube',
				'TNK' => 'Tank',
				'UNT' => 'Unit',
				'VPK' => 'Van Pack',
				'WRP' => 'Wrapped',
		),
		'requester_type' => array (
				'10' => 'Prepaid (requires bill to address)',
				'20' => 'Bill to Consignee (requires bill to address)',
				'30' => 'Bill to Third Party (requires bill to address)',
				'40' => 'Freight Collect',
		//'billto'    => 'Bill To (requires a Bill To address)',
		),
		);

		if (!isset ($codes[$type])) {
			//            throw Mage::exception('Mage_Shipping', Mage::helper('usa')->__('Invalid UPS CGI code type: %s', $type));
			return false;
		}
		elseif ('' === $code) {
			return $codes[$type];
		}

		if (!isset ($codes[$type][$code])) {
			return false;
		} else {
			return $codes[$type][$code];
		}
	}

 public function toOptionArray()
 {

	$arr = [];
        foreach ($this->getCode('method') as $k=>$v) {
            $arr[] = ['value'=>$k, 'label'=>$v];
        }
        return $arr;

 }
}