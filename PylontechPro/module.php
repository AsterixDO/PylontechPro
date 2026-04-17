<?php

class PylontechPro extends IPSModule
{

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger("Interval", 10);

        $this->RegisterTimer(
            "Update",
            0,
            'IPS_RequestAction($_IPS["TARGET"], "Update", 0);'
        );

        $this->RegisterVariableInteger("PackCount","Battery Packs");

        $this->RegisterVariableFloat("TotalVoltage","Total Voltage","~Volt");
        $this->RegisterVariableFloat("TotalCurrent","Total Current","~Ampere");
        $this->RegisterVariableFloat("TotalPower","Total Power","~Watt");
        $this->RegisterVariableFloat("TotalSOC","Total SOC","~Intensity.100");

        $this->RegisterVariableFloat("MinCell","Min Cell Voltage","~Volt");
        $this->RegisterVariableFloat("MaxCell","Max Cell Voltage","~Volt");
        $this->RegisterVariableFloat("CellDelta","Cell Delta","~Volt");
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger("Interval") * 1000;
        $this->SetTimerInterval("Update",$interval);
    }

    public function RequestAction($Ident, $Value)
    {
        if($Ident == "Update")
        {
            $this->Update();
        }
    }

    public function Update()
    {
        $request="~20024642E002FF00";

        $this->SendDebug("Request",$request,0);

        $this->SendDataToParent(json_encode(
        [
            "DataID"=>"{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
            "Buffer"=>$request
        ]));
    }

    public function ReceiveData($JSONString)
    {
        $data=json_decode($JSONString);
        $buffer=$data->Buffer;

        if(strlen($buffer)<40)
            return;

        $packCount=hexdec($buffer[17].$buffer[18]);
        $this->SetValue("PackCount",$packCount);

        $offset=19;

        $totalVoltage=0;
        $totalCurrent=0;
        $totalSOC=0;

        $cellMin=100;
        $cellMax=0;

        for($pack=1;$pack<=$packCount;$pack++)
        {
            $cellCount=hexdec($buffer[$offset].$buffer[$offset+1]);
            $offset+=2;

            for($cell=1;$cell<=$cellCount;$cell++)
            {
                $voltage=hexdec(
                    $buffer[$offset].
                    $buffer[$offset+1].
                    $buffer[$offset+2].
                    $buffer[$offset+3]
                )/1000;

                $offset+=4;

                if($voltage<$cellMin) $cellMin=$voltage;
                if($voltage>$cellMax) $cellMax=$voltage;

                $ident="P".$pack."_Cell".$cell;

                if(@$this->GetIDForIdent($ident)===false)
                    $this->RegisterVariableFloat($ident,"Pack".$pack." Cell".$cell,"~Volt");

                $this->SetValue($ident,$voltage);
            }

            $tempCount=hexdec($buffer[$offset].$buffer[$offset+1]);
            $offset+=2;

            for($t=1;$t<=$tempCount;$t++)
            {
                $temp=(hexdec(
                    $buffer[$offset].
                    $buffer[$offset+1].
                    $buffer[$offset+2].
                    $buffer[$offset+3]
                )-2731)/10;

                $offset+=4;

                $ident="P".$pack."_Temp".$t;

                if(@$this->GetIDForIdent($ident)===false)
                    $this->RegisterVariableFloat($ident,"Pack".$pack." Temp".$t,"~Temperature");

                $this->SetValue($ident,$temp);
            }

            $current=hexdec(
                $buffer[$offset].
                $buffer[$offset+1].
                $buffer[$offset+2].
                $buffer[$offset+3]
            );

            if($current>=32768)
                $current-=65536;

            $current=$current/10;

            $offset+=4;

            $totalCurrent+=$current;

            $ident="P".$pack."_Current";

            if(@$this->GetIDForIdent($ident)===false)
                $this->RegisterVariableFloat($ident,"Pack".$pack." Current","~Ampere");

            $this->SetValue($ident,$current);

            $voltage=hexdec(
                $buffer[$offset].
                $buffer[$offset+1].
                $buffer[$offset+2].
                $buffer[$offset+3]
            )/1000;

            $offset+=4;

            $totalVoltage+=$voltage;

            $ident="P".$pack."_Voltage";

            if(@$this->GetIDForIdent($ident)===false)
                $this->RegisterVariableFloat($ident,"Pack".$pack." Voltage","~Volt");

            $this->SetValue($ident,$voltage);

            $remain=hexdec(
                $buffer[$offset].
                $buffer[$offset+1].
                $buffer[$offset+2].
                $buffer[$offset+3]
            )/1000;

            $offset+=4;

            $total=hexdec(
                $buffer[$offset].
                $buffer[$offset+1].
                $buffer[$offset+2].
                $buffer[$offset+3]
            )/1000;

            $offset+=4;

            $soc=0;

            if($total>0)
                $soc=($remain/$total)*100;

            $totalSOC+=$soc;

            $ident="P".$pack."_SOC";

            if(@$this->GetIDForIdent($ident)===false)
                $this->RegisterVariableFloat($ident,"Pack".$pack." SOC","~Intensity.100");

            $this->SetValue($ident,$soc);

            $cycles=hexdec(
                $buffer[$offset].
                $buffer[$offset+1].
                $buffer[$offset+2].
                $buffer[$offset+3]
            );

            $offset+=4;

            $ident="P".$pack."_Cycles";

            if(@$this->GetIDForIdent($ident)===false)
                $this->RegisterVariableInteger($ident,"Pack".$pack." Cycles");

            $this->SetValue($ident,$cycles);

            $offset+=2;
        }

        if($packCount>0)
            $totalSOC=$totalSOC/$packCount;

        $power=$totalVoltage*$totalCurrent;

        $this->SetValue("TotalVoltage",$totalVoltage);
        $this->SetValue("TotalCurrent",$totalCurrent);
        $this->SetValue("TotalPower",$power);
        $this->SetValue("TotalSOC",$totalSOC);

        $this->SetValue("MinCell",$cellMin);
        $this->SetValue("MaxCell",$cellMax);
        $this->SetValue("CellDelta",$cellMax-$cellMin);
    }

}
?>
