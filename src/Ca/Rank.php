<?php

namespace HnuQuery\Ca;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Rank
{
    public string $allGpa;
    public string $allGpaRank;
    public string $allWeighted;
    public string $allWeightedRank;
    public string $allArithmetic;
    public string $allArithmeticRank;
    public string $mustGpa;
    public string $mustWeighted;
    public string $mustArithmetic;
    public string $coreGpaRank;
    public string $coreWeightedRank;
    public string $coreArithmeticRank;

    private function __construct(array $data)
    {
        $this->allGpa = $data['allGpa'];
        $this->allGpaRank = $data['allGpaRank'];
        $this->allWeighted = $data['allWeighted'];
        $this->allWeightedRank = $data['allWeightedRank'];
        $this->allArithmetic = $data['allArithmetic'];
        $this->allArithmeticRank = $data['allArithmeticRank'];
        $this->mustGpa = $data['mustGpa'];
        $this->mustWeighted = $data['mustWeighted'];
        $this->mustArithmetic = $data['mustArithmetic'];
        $this->coreGpaRank = $data['coreGpaRank'];
        $this->coreWeightedRank = $data['coreWeightedRank'];
        $this->coreArithmeticRank = $data['coreArithmeticRank'];
    }

    public static function getGradeRank(CaToken $caToken): self
    {
        $client = HttpClient::getClient();
        $headers = $caToken->getHeaders();

        $templateUrl = sprintf(
            'https://ca.hnu.edu.cn/student/student/caTemplate/preview_file?templateId=%s&isbzf=0&kcxz=&xfjd=&xzkc=',
            CaToken::UNDERGRADUATE_MAJOR_ALL_TEMPLATE_ID
        );

        $response = $client->get($templateUrl, ['headers' => $headers]);
        $body = $response->getBody()->getContents();

        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '证书模板响应格式错误');
        }

        // 检查响应码
        $code = $json['code'] ?? 0;
        if ($code !== 200) {
            throw HnuQueryException::parseError($body, '获取证书模板失败: ' . ($json['message'] ?? '未知错误'));
        }

        $fileName = $json['message'] ?? '';
        if (empty($fileName)) {
            throw HnuQueryException::parseError($body, '无法获取证书文件名');
        }

        $fileUrl = sprintf('https://ca.hnu.edu.cn/student/sys/common/view/%s', $fileName);

        $response = $client->get($fileUrl, ['headers' => $headers]);
        $pdfContent = $response->getBody()->getContents();

        $text = self::extractTextFromPdf($pdfContent);

        // 正则表达式
        $pattern = '/平均学分绩点排名 ([0-9\/]+).*平均学分绩点 ([0-9.]+).*核心课程平均学分绩点排名 ([0-9\/]+).*必修课平均学分绩点 ([0-9.]+).*课程算术平均成绩排名 ([0-9\/]+).*算术平均分 ([0-9.]+).*核心课程算术平均成绩排名 ([0-9\/]+).*必修课算术平均分 ([0-9.]+).*学分加权平均成绩排名 ([0-9\/]+).*加权平均分 ([0-9.]+).*核心课程学分加权平均成绩排名 ([0-9\/]+).*必修课加权平均分 ([0-9.]+)/s';

        if (!preg_match($pattern, $text, $matches)) {
            throw HnuQueryException::parseError(substr($text, 0, 1000), '无法从PDF中解析排名信息');
        }

        return new self([
            'allGpaRank' => $matches[1],
            'allGpa' => $matches[2],
            'coreGpaRank' => $matches[3],
            'mustGpa' => $matches[4],
            'allArithmeticRank' => $matches[5],
            'allArithmetic' => $matches[6],
            'coreArithmeticRank' => $matches[7],
            'mustArithmetic' => $matches[8],
            'allWeightedRank' => $matches[9],
            'allWeighted' => $matches[10],
            'coreWeightedRank' => $matches[11],
            'mustWeighted' => $matches[12],
        ]);
    }

    private static function extractTextFromPdf(string $pdfContent): string
    {
        //使用pdf-extract库的提取逻辑
        // Rust版本使用 pdf_extract::extract_text_from_mem(&bytes)
        
        if (class_exists('\Smalot\PdfParser\Parser')) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseContent($pdfContent);
            return $pdf->getText();
        }

        throw HnuQueryException::unexpectedError('需要安装 smalot/pdfparser 库来解析PDF文件: composer require smalot/pdfparser');
    }
}
