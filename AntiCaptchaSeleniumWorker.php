<?php

namespace Bankon\IntegrationBundle\Util;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;

/**
 * Class AntiCaptchaSeleniumWorker
 *
 * Класс для получения и распознавания капчи сервисом anti-captcha.com.
 * Работает с библиотекой Selenium WebDriver
 */
class AntiCaptchaSeleniumWorker
{
    /**
     * @var string $captchaPath Путь временного сохранения изображения капчи
     */
    protected $captchaPath;

    /**
     * @var string $resultCaptcha Результат распознавания капчи
     */
    protected $resultCaptcha;

    /**
     * @var string $key Ключ для anti-captcha.com
     */
    protected $key;

    /**
     * @var AntiCaptchaManager
     */
    private $antiCaptchaManager;

    public function __construct(AntiCaptchaManager $antiCaptchaManager, $anti_captcha_key, $captchaPath = NULL)
    {
        if(is_null($captchaPath)) {
            $captchaPath = $_SERVER['DOCUMENT_ROOT'];
        }
        $rndName =  md5(microtime() . rand(0, 9999));
        $this->captchaPath = $captchaPath . $rndName . '.png';

        $this->key = $anti_captcha_key;
        $this->antiCaptchaManager = $antiCaptchaManager;
    }

   /**
    * Функция получения скриншота всей страницы
    *
    * @param RemoteWebDriver $webDriver Объект WebDriver всей страницы
    */
    protected function captureScreenshot($webDriver)
    {
        $webDriver->TakeScreenshot($this->captchaPath);
    }

    /**
     * Функция получения изображения капчи из общего скриншота страницы
     *
     * @param RemoteWebElement $element Объект, содержащий изображение капчи на странице
     */
    protected function createImageByElement($element)
    {
        $element_width = $element->getSize()->getWidth();
        $element_height = $element->getSize()->getHeight();

        $element_src_x = $element->getLocation()->getX();
        $element_src_y = $element->getLocation()->getY();

        $src = imagecreatefrompng($this->captchaPath);
        $dest = imagecreatetruecolor($element_width, $element_height);

        imagecopy($dest, $src, 0, 0, $element_src_x, $element_src_y, $element_width, $element_height);
        imagepng($dest, $this->captchaPath);
    }

    /**
     * Функция распознавания капчи сервисом anti-captcha.com.
     */
    protected function recognizeCaptcha()
    {
        $this->resultCaptcha = $this->antiCaptchaManager->recognize($this->captchaPath);
    }

    /**
     * Функция удаления сохраненного изображения с капчей
     */
    protected function deleteCaptchaImage()
    {
        unlink($this->captchaPath);
    }

    /**
     * Распознавание капчи и получение результата
     *
     * @param RemoteWebDriver $currentPage Текущая страница
     * @param RemoteWebElement $captcha_image Объект, содержащий изображение капчи
     *
     * @return string
     */
    public function startRecognizeCaptcha($currentPage, $captcha_image)
    {
        $this->captureScreenshot($currentPage);
        $this->createImageByElement($captcha_image);
        $this->recognizeCaptcha();
        $this->deleteCaptchaImage();

        return $this->resultCaptcha;
    }

}
