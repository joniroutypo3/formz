<?php
/*
 * 2016 Romain CANON <romain.hydrocanon@gmail.com>
 *
 * This file is part of the TYPO3 Formz project.
 * It is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, either
 * version 3 of the License, or any later version.
 *
 * For the full copyright and license information, see:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Romm\Formz\AssetHandler\JavaScript;

use Romm\Formz\Condition\Processor\JavaScriptProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This asset handler generates the JavaScript code used the activate specific
 * validation rules for a given field. For example, you may want to enable the
 * rule "required" of a given field only under certain circumstances.
 */
class FieldsValidationActivationJavaScriptAssetHandler extends AbstractJavaScriptAssetHandler
{

    /**
     * Main function of this asset handler.
     *
     * @return string
     */
    public function getFieldsValidationActivationJavaScriptCode()
    {
        $javaScriptBlocks = [];

        /** @var JavaScriptProcessor $javaScriptProcessor */
        $javaScriptProcessor = GeneralUtility::makeInstance(JavaScriptProcessor::class, $this->getFormObject());

        foreach ($this->getFormConfiguration()->getFields() as $fieldName => $field) {
            foreach ($field->getValidation() as $validatorName => $validation) {
                $fieldJavaScript = $javaScriptProcessor->getFieldValidationActivationConditionTree($field, $validation);

                if (null !== $fieldJavaScript) {
                    $fieldConditionExpression = [];
                    foreach ($fieldJavaScript as $javaScript) {
                        if (false === empty($javaScript)) {
                            $fieldConditionExpression[] = 'flag = flag || (' . $javaScript . ');';
                        }
                    }

                    $javaScriptBlocks[] = $this->getSingleFieldActivationConditionFunction($fieldName, $validatorName, $fieldConditionExpression);
                }
            }
        }

        $javaScriptBlocks = implode(CRLF, $javaScriptBlocks);
        $formName = GeneralUtility::quoteJSvalue($this->getFormObject()->getName());

        return <<<JS
(function() {
    Formz.Form.get(
        $formName,
        function(form) {
            var field = null;

$javaScriptBlocks

            form.refreshAllFields();
        }
    );
})();
JS;
    }

    /**
     * This function is just here to make the class more readable.
     *
     * @param string $fieldName                Name of the field.
     * @param string $validatorName            Name of the validation rule.
     * @param array  $fieldConditionExpression Array containing the JavaScript condition expression for the field.
     * @return string
     */
    protected function getSingleFieldActivationConditionFunction($fieldName, $validatorName, $fieldConditionExpression)
    {
        $fieldName = GeneralUtility::quoteJSvalue($fieldName);
        $validatorName = GeneralUtility::quoteJSvalue($validatorName);
        $fieldConditionExpression = implode(CRLF . str_repeat(' ', 20), $fieldConditionExpression);

        return <<<JS
        field = form.getFieldByName($fieldName);

        if (null !== field) {
            field.addActivationConditionForValidator(
                '__auto',
                $validatorName,
                function (field, continueValidation) {
                    var flag = false;
                    $fieldConditionExpression
                    continueValidation(flag);
                }
            );
        }
JS;
    }
}
