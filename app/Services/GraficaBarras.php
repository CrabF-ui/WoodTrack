<?php

namespace App\Services;

use InvalidArgumentException;

class GraficaBarras
{
    public const FORMATO_ENTERO = 'entero';

    public const FORMATO_PESOS = 'pesos';

    public const ORIENTACION_HORIZONTAL = 'horizontal';

    public const ORIENTACION_VERTICAL = 'vertical';

    private const ANCHO = 700;

    private const LIMITE_ETIQUETA = 28;

    public function generar(
        string $titulo,
        array $etiquetas,
        array $valores,
        string $formato = self::FORMATO_ENTERO,
        string $orientacion = self::ORIENTACION_HORIZONTAL,
    ): ?string {
        if (count($etiquetas) !== count($valores)) {
            throw new InvalidArgumentException('Las etiquetas y los valores deben tener la misma cantidad de elementos.');
        }

        $valores = array_map(fn ($valor): float => max(0, (float) $valor), $valores);

        if ($valores === [] || max($valores) <= 0) {
            return null;
        }

        $svg = $orientacion === self::ORIENTACION_VERTICAL
            ? $this->vertical($titulo, $etiquetas, $valores, $formato)
            : $this->horizontal($titulo, $etiquetas, $valores, $formato);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function horizontal(string $titulo, array $etiquetas, array $valores, string $formato): string
    {
        $alto = max(210, 82 + count($valores) * 42);
        $izquierda = 205;
        $derecha = 105;
        $arriba = 62;
        $anchoGrafica = self::ANCHO - $izquierda - $derecha;
        $maximo = max($valores);
        $elementos = $this->inicioSvg($titulo, $alto);

        for ($guia = 0; $guia <= 4; $guia++) {
            $x = $izquierda + ($anchoGrafica * $guia / 4);
            $elementos .= '<line x1="'.$x.'" y1="'.$arriba.'" x2="'.$x.'" y2="'.($alto - 28).'" stroke="#d2d2d2" stroke-width="1"/>';
        }

        foreach ($valores as $indice => $valor) {
            $y = $arriba + $indice * 42;
            $anchoBarra = $anchoGrafica * $valor / $maximo;
            $etiqueta = $this->escapar($this->truncar((string) $etiquetas[$indice]));
            $textoValor = $this->escapar($this->formatear($valor, $formato));
            $elementos .= '<text x="'.($izquierda - 10).'" y="'.($y + 18).'" text-anchor="end" font-size="12">'.$etiqueta.'</text>';
            $elementos .= '<rect x="'.$izquierda.'" y="'.$y.'" width="'.$anchoBarra.'" height="24" fill="#555" stroke="#111" stroke-width="1"/>';
            $elementos .= '<text x="'.($izquierda + $anchoBarra + 7).'" y="'.($y + 17).'" font-size="12" font-weight="bold">'.$textoValor.'</text>';
        }

        return $elementos.'</g></svg>';
    }

    private function vertical(string $titulo, array $etiquetas, array $valores, string $formato): string
    {
        $alto = 330;
        $izquierda = 62;
        $arriba = 62;
        $base = 265;
        $anchoGrafica = self::ANCHO - $izquierda - 35;
        $altoGrafica = $base - $arriba;
        $maximo = max($valores);
        $espacio = $anchoGrafica / count($valores);
        $anchoBarra = min(120, $espacio * 0.5);
        $elementos = $this->inicioSvg($titulo, $alto);

        for ($guia = 0; $guia <= 4; $guia++) {
            $y = $base - ($altoGrafica * $guia / 4);
            $valorGuia = $maximo * $guia / 4;
            $elementos .= '<line x1="'.$izquierda.'" y1="'.$y.'" x2="'.(self::ANCHO - 35).'" y2="'.$y.'" stroke="#d2d2d2" stroke-width="1"/>';
            $elementos .= '<text x="'.($izquierda - 7).'" y="'.($y + 4).'" text-anchor="end" font-size="11">'.$this->escapar($this->formatear($valorGuia, $formato)).'</text>';
        }

        foreach ($valores as $indice => $valor) {
            $centro = $izquierda + $espacio * ($indice + 0.5);
            $altoBarra = $altoGrafica * $valor / $maximo;
            $y = $base - $altoBarra;
            $elementos .= '<rect x="'.($centro - $anchoBarra / 2).'" y="'.$y.'" width="'.$anchoBarra.'" height="'.$altoBarra.'" fill="#555" stroke="#111" stroke-width="1"/>';
            $elementos .= '<text x="'.$centro.'" y="'.max(52, $y - 7).'" text-anchor="middle" font-size="12" font-weight="bold">'.$this->escapar($this->formatear($valor, $formato)).'</text>';
            $elementos .= '<text x="'.$centro.'" y="287" text-anchor="middle" font-size="12">'.$this->escapar($this->truncar((string) $etiquetas[$indice])).'</text>';
        }

        return $elementos.'</g></svg>';
    }

    private function inicioSvg(string $titulo, int $alto): string
    {
        $fuente = $this->escapar((string) config('reportes.fuente'));

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::ANCHO.'" height="'.$alto.'" viewBox="0 0 '.self::ANCHO.' '.$alto.'">'
            .'<rect width="100%" height="100%" fill="#fff"/>'
            .'<g fill="#222" font-family="'.$fuente.', sans-serif">'
            .'<text x="'.(self::ANCHO / 2).'" y="27" text-anchor="middle" font-size="15" font-weight="bold">'.$this->escapar($titulo).'</text>'
            .'</g><g fill="#222" font-family="'.$fuente.', sans-serif">';
    }

    private function truncar(string $texto): string
    {
        return mb_strlen($texto) > self::LIMITE_ETIQUETA
            ? mb_substr($texto, 0, self::LIMITE_ETIQUETA - 1).'…'
            : $texto;
    }

    private function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function formatear(float $valor, string $formato): string
    {
        if ($formato === self::FORMATO_PESOS) {
            return '$'.number_format($valor, 2, '.', ',');
        }

        return fmod($valor, 1.0) === 0.0
            ? number_format($valor, 0, '.', ',')
            : rtrim(rtrim(number_format($valor, 2, '.', ','), '0'), '.');
    }
}
