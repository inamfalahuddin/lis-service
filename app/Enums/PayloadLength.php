<?php

namespace App\Enums;

class PayloadLength
{
    // MSH Header
    const PRODUCT = 15;
    const VERSION = 15;
    const USER_ID = 15;
    const KEY = 15;

    // PID (Patient Data)
    const PMRN = 15;
    const PNAME = 100;
    const SEX = 1;
    const BIRTH_DT = 10;
    const ADDRESS = 100;
    const NO_TLP = 25;
    const NO_HP = 25;
    const EMAIL = 25;
    const NIK = 25;

    // OBR (Order Data)
    const ORDER_CONTROL = 2;
    const PTYPE = 2;
    const REG_NO = 15;
    const ORDER_LAB = 15;
    const PROVIDER_ID = 15;
    const PROVIDER_NAME = 50;
    const ORDER_DATE = 19;
    const CLINICIAN_ID = 15;
    const CLINICIAN_NAME = 100;
    const BANGSAL_ID = 15;
    const BANGSAL_NAME = 100;
    const BED_ID = 10;
    const BED_NAME = 20;
    const CLASS_ID = 10;
    const CLASS_NAME = 15;
    const CITO = 1;
    const MED_LEGAL = 1;
    const USER_ID_OBR = 30;
    const RESERVE = 100;

    // Order Test
    const ORDER_TEST = 20;
}
