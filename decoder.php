<?php

function readVarint($data, &$pos)
{
    $result = 0;
    $shift = 0;
    $length = strlen($data);

    while ($pos < $length) {
        $byte = ord($data[$pos++]);

        $result += ($byte & 0x7F) * (2 ** $shift);

        if (($byte & 0x80) === 0) {
            return $result;
        }

        $shift += 7;

        if ($shift > 63) {
            throw new Exception("Invalid varint");
        }
    }

    throw new Exception("Unexpected end of data");
}


function readField($data, &$pos, $wire)
{
    $length = strlen($data);

    switch ($wire) {

        case 0:
            return readVarint($data, $pos);

        case 1:
            if ($pos + 8 > $length) {
                throw new Exception("Invalid fixed64");
            }

            $value = substr($data, $pos, 8);
            $pos += 8;

            return unpack("d", $value)[1];

        case 2:
            $len = readVarint($data, $pos);

            if ($pos + $len > $length) {
                throw new Exception("Invalid length");
            }

            $value = substr($data, $pos, $len);
            $pos += $len;

            return $value;

        case 5:
            if ($pos + 4 > $length) {
                throw new Exception("Invalid fixed32");
            }

            $value = substr($data, $pos, 4);
            $pos += 4;

            return unpack("g", $value)[1];

        default:
            throw new Exception("Unsupported protobuf wire type: " . $wire);
    }
}


/*
 * Position
 *
 * latitude  = 1
 * longitude = 2
 * bearing   = 3
 * speed     = 5
 */
function parsePosition($data)
{
    $result = [];
    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);

        switch ($field) {

            case 1:
                $result["lat"] = (float)$value;
                break;

            case 2:
                $result["lon"] = (float)$value;
                break;

            case 3:
                $result["bearing"] = (float)$value;
                break;

            case 5:
                $result["speed"] = (float)$value;
                break;
        }
    }

    return $result;
}


/*
 * Trip
 *
 * trip_id      = 1
 * route_id     = 5
 * direction_id = 6
 */
function parseTrip($data)
{
    $result = [];
    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);

        if ($wire == 2) {

            switch ($field) {

                case 1:
                    $result["trip_id"] = $value;
                    break;

                case 5:
                    $result["route_id"] = $value;
                    break;

                case 6:
                    $result["direction_id"] = $value;
                    break;
            }
        }
    }

    return $result;
}


/*
 * VehicleDescriptor
 *
 * id            = 1
 * label         = 2
 * license_plate = 3
 */
function parseVehicle($data)
{
    $result = [];
    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);

        if ($wire == 2) {

            switch ($field) {

                case 1:
                    $result["id"] = $value;
                    break;

                case 2:
                    $result["label"] = $value;
                    break;

                case 3:
                    $result["license_plate"] = $value;
                    break;
            }
        }
    }

    return $result;
}


/*
 * VehiclePosition
 *
 * trip            = 1
 * position        = 2
 * current_stop_sequence = 3
 * stop_id         = 7
 * current_status  = 4
 * timestamp       = 5
 * vehicle          = 8
 */
function parseVehiclePosition($data)
{
    $result = [];

    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);

        switch ($field) {

            case 1:

                if ($wire == 2) {
                    $result["trip"] = parseTrip($value);
                }

                break;


            case 2:

                if ($wire == 2) {
                    $result["position"] = parsePosition($value);
                }

                break;


            case 3:

                $result["current_stop_sequence"] = $value;

                break;


            case 4:

                $result["current_status"] = $value;

                break;


            case 5:

                $result["timestamp"] = $value;

                break;


            case 7:

                if ($wire == 2) {
                    $result["stop_id"] = $value;
                }

                break;


            case 8:

                if ($wire == 2) {
                    $result["vehicle"] = parseVehicle($value);
                }

                break;
        }
    }

    return $result;
}


/*
 * FeedEntity
 *
 * id      = 1
 * vehicle = 4
 */
function parseEntity($data)
{
    $result = [];

    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);

        switch ($field) {

            case 1:

                if ($wire == 2) {
                    $result["entity_id"] = $value;
                }

                break;


            case 4:

                if ($wire == 2) {
                    $result["vehicle"] = parseVehiclePosition($value);
                }

                break;
        }
    }

    return $result;
}


/*
 * Главная функция.
 *
 * FeedMessage:
 *
 * header  = 1
 * entity  = 2
 */
function decodeVehicleFeed($data)
{
    $vehicles = [];

    $feedTimestamp = time();

    $pos = 0;
    $length = strlen($data);

    while ($pos < $length) {

        $key = readVarint($data, $pos);

        $field = $key >> 3;
        $wire  = $key & 7;

        $value = readField($data, $pos, $wire);


        /*
         * FeedHeader
         */
        if ($field == 1 && $wire == 2) {

            $headerPos = 0;
            $headerLength = strlen($value);

            while ($headerPos < $headerLength) {

                $headerKey = readVarint($value, $headerPos);

                $headerField = $headerKey >> 3;
                $headerWire  = $headerKey & 7;

                $headerValue =
                    readField($value, $headerPos, $headerWire);

                /*
                 * timestamp = field 3
                 */
                if ($headerField == 3 && $headerWire == 0) {
                    $feedTimestamp = $headerValue;
                }
            }
        }


        /*
         * FeedEntity
         */
        if ($field == 2 && $wire == 2) {

            $entity = parseEntity($value);

            if (isset($entity["vehicle"])) {

                $v = $entity["vehicle"];

                $position = $v["position"] ?? [];
                $trip     = $v["trip"] ?? [];
                $vehicle  = $v["vehicle"] ?? [];


                $item = [

                    "id" =>
                        $vehicle["id"]
                        ?? ($entity["entity_id"] ?? null),

                    "label" =>
                        $vehicle["label"] ?? null,

                    "lat" =>
                        $position["lat"] ?? null,

                    "lon" =>
                        $position["lon"] ?? null,

                    "bearing" =>
                        $position["bearing"] ?? null,

                    "speed" =>
                        $position["speed"] ?? null,

                    "speed_kmh" =>
                        isset($position["speed"])
                        ? round($position["speed"] * 3.6, 2)
                        : null,

                    "route_id" =>
                        $trip["route_id"] ?? null,

                    "trip_id" =>
                        $trip["trip_id"] ?? null,

                    "direction_id" =>
                        $trip["direction_id"] ?? null,

                    "stop_id" =>
                        $v["stop_id"] ?? null,

                    "current_stop_sequence" =>
                        $v["current_stop_sequence"] ?? null,

                    "current_status" =>
                        $v["current_status"] ?? null,

                    "timestamp" =>
                        $v["timestamp"] ?? null
                ];


                $vehicles[] = $item;
            }
        }
    }


    return [

        "updated" => $feedTimestamp,

        "vehicle_count" => count($vehicles),

        "vehicles" => $vehicles

    ];
}

?>