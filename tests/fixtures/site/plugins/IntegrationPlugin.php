<?php
if (class_exists('IntegrationPlugin', false)) {
    return;
}

class IntegrationPlugin extends AbstractPicoPlugin
{
    const API_VERSION = 3;
    protected $enabled = true;

    public function onTwigRegistered(\Twig\Environment &$twig)
    {
        $twig->addFunction(new \Twig\TwigFunction('registered', function () {
            return 'Plugin registered';
        }));
    }
}
