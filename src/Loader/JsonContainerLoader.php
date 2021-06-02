<?php

namespace SunnyFlail\DI;

final class JsonContainerLoader implements IContainerLoader
{

    private array $entries;

    public function loadEntries(): array
    {
        return $this->entries;
    }

    public static function fromJson(string $json): IContainerLoader
    {
        $data = json_decode($json, true);
        try{
            $entries = [];
            foreach($data as $className => $settings) {
                $entries[$className] = new Entry($className, $settings);
            }
        } catch (LoaderException $e) {
            throw $e;
        } catch (\Throwable $t) {
            throw new LoaderException("Container configuration corrupted!");
        }
    }

}