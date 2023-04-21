<?php

# Здесь содержится пространство имён...

// Класс для работы с моделью Instruments
class InstrumentsRepository extends Repository
{
    protected function getTableName(): string
    {
        return "instruments";
    }

    protected function getIdName(): string
    {
        return "instruments_id";
    }

    protected function getEntityClass(): string
    {
        return Instruments::class;
    }

    public function getImgDir(): string
    {
        return "instrumentsLogo";
    }

    public function getDefaultImgName(): string
    {
        return "default.jpg";
    }

    public function getLogoExists($logoName) : string
    {
        if (strlen($logoName) > 0 && file_exists('images/' . $this->getImgDir() . '/' . $logoName)){
            return $logoName;
        }else{
            return $this->getDefaultImgName();
        }
    }

    public function getByBrokerId(int $brokerId, string $features = null)
    {
        $from = match ($features) {
            'onlyDividends' => 'dividends',
            default => 'operations',
        };

        $sql = "SELECT * 
                FROM `instruments` 
                WHERE `instruments_id` IN 
                    (
                    SELECT DISTINCT `instruments_id` 
                    FROM `{$from}` 
                    WHERE `brokers_id` = :brokers_id
                    ) 
                ORDER BY `instruments_code`";

        return Admiral::call()->db->queryAll($sql, ['brokers_id' => $brokerId]);
    }

    public function getSumBroker(Brokers $brokers)
    {
        $instruments = $this->getByBrokerId($brokers->brokers_id);

        if (empty($instruments)){
            return false;
        }

        $result = [
            'sum' => 0,
            'ids' => [],
            ];

        foreach ($instruments as $instrument){
            $resultInstrument = $this->getResultInstrument($brokers, $instrument['instruments_id']);
            $result['sum'] += $resultInstrument['total'];
            $result['ids'][] = $instrument['instruments_id'];
        }

        return $result;
    }

    public function getResultInstrument(Brokers $brokers, int $instrumentsId){
        $operations = Admiral::call()->operationsRepository
            ->where('brokers_id', $brokers->brokers_id)
            ->andWhere('instruments_id', $instrumentsId)
            ->getAll();

        if (empty($operations)){
            return false;
        }

        $result = [
            'operations' => [],
            'avg' => 0,
            'total' => 0,
            'totalQuantity' => 0,
            'dividends' => 0
        ];

        foreach ($operations as $value) {
            if ($value['operations_type'] == "buy") {
                $result['operations'][] = [
                    'operations_date' => $value['operations_date'],
                    'operations_quantity' => $value['operations_quantity'],
                    'operations_price' => $value['operations_price'],
                    'saleQuantity' => 0,
                    'avgSalePrice' => 0,
                    'tax' => 0,
                    'commission' => round($value['operations_quantity'] * $value['operations_price'] * $brokers->brokers_commission, 2),
                    'sum' => 0,
                ];
            }
        }

        $dividends = Admiral::call()->dividendsRepository
            ->where('instruments_id', $instrumentsId)
            ->andWhere('brokers_id', $brokers->brokers_id)
            ->getAll();

        foreach ($result['operations'] as $resultKey => $resultValue){
            foreach ($operations as $dataKey => $dataValue){
                if ($dataValue['operations_type'] == 'sale' && $dataValue['operations_quantity'] > 0) {
                    if ($dataValue['operations_quantity'] <= $resultValue['operations_quantity'] - $result['operations'][$resultKey]['saleQuantity']){
                        $result['operations'][$resultKey]['saleQuantity'] += $dataValue['operations_quantity'];
                        $result['operations'][$resultKey]['sum'] += $dataValue['operations_price'] * $dataValue['operations_quantity'];

                        $result['operations'][$resultKey]['avgSalePrice'] = $result['operations'][$resultKey]['sum'] / $result['operations'][$resultKey]['saleQuantity'];

                        $operations[$dataKey]['operations_quantity'] -= $dataValue['operations_quantity'];
                    }else{
                        $quantity = $resultValue['operations_quantity'] - $result['operations'][$resultKey]['saleQuantity'];
                        $result['operations'][$resultKey]['saleQuantity'] += $quantity;
                        $result['operations'][$resultKey]['sum'] += $dataValue['operations_price'] * $quantity;

                        $result['operations'][$resultKey]['avgSalePrice'] = $result['operations'][$resultKey]['sum'] / $result['operations'][$resultKey]['saleQuantity'];

                        $operations[$dataKey]['operations_quantity'] -= $quantity;
                        break;
                    }
                }
            }
            if (!empty($result['operations'][$resultKey]['saleQuantity'])){
                $result['operations'][$resultKey]['total'] = $result['operations'][$resultKey]['saleQuantity'] * $result['operations'][$resultKey]['avgSalePrice'] - $result['operations'][$resultKey]['saleQuantity'] * $result['operations'][$resultKey]['operations_price'];
                $result['operations'][$resultKey]['commission'] += round($result['operations'][$resultKey]['sum'] * $brokers->brokers_commission, 2);

                if ($result['operations'][$resultKey]['total'] > 0){
                    $result['operations'][$resultKey]['tax'] = round($result['operations'][$resultKey]['total'] * 0.13, 2);
                    $result['operations'][$resultKey]['total'] -= $result['operations'][$resultKey]['tax'];
                    $result['operations'][$resultKey]['total'] -= $result['operations'][$resultKey]['commission'];
                }

                $result['operations'][$resultKey]['procent'] = round((($result['operations'][$resultKey]['saleQuantity'] * $result['operations'][$resultKey]['avgSalePrice'] - $result['operations'][$resultKey]['commission'] - $result['operations'][$resultKey]['tax']) / ($result['operations'][$resultKey]['saleQuantity'] * $result['operations'][$resultKey]['operations_price'])) * 100 - 100, 2);
            }
            $result['total'] += $result['operations'][$resultKey]['operations_quantity'] * $result['operations'][$resultKey]['operations_price'] - $result['operations'][$resultKey]['saleQuantity'] * $result['operations'][$resultKey]['avgSalePrice'] + $result['operations'][$resultKey]['tax'] + $result['operations'][$resultKey]['commission'];
            $result['totalQuantity'] += $result['operations'][$resultKey]['operations_quantity'] - $result['operations'][$resultKey]['saleQuantity'];
        }

        foreach ($dividends as $value){
            $result['dividends'] += $value['dividends_amount'];
        }

        $result['total'] -= $result['dividends'];
        $result['avg'] = ($result['totalQuantity']) ? $result['total'] / $result['totalQuantity'] : $result['total'];

        return $result;
    }
}