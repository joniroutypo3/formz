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

namespace Romm\Formz\AssetHandler\Html;

use Romm\Formz\AssetHandler\AbstractAssetHandler;
use Romm\Formz\Error\FormResult;
use Romm\Formz\Form\FormInterface;
use Romm\Formz\Validation\Validator\Form\AbstractFormValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * This asset handler generates several data attributes which will be added to
 * the form element in the Fluid template. Most of these data attributes are
 * directly bound to fields and their properties.
 *
 * Example of data attributes:
 *  - Fields values: when a field changes, its new value will be indicated in
 *    the form with the attribute: `formz-value-{field name}="value"`.
 *  - Fields validation: when a field is considered as valid (it passed all its
 *    validation rules), the form gets the attribute: `formz-valid-{field name}`.
 *  - Fields errors: when a field validation fails with an error, the form gets
 *    the attribute: `formz-error-{field name}-{name of the error}`.
 */
class DataAttributesAssetHandler extends AbstractAssetHandler
{

    /**
     * Handles the data attributes containing the values of the form fields.
     *
     * Example:
     *  formz-value-color="blue"
     *
     * @param FormInterface|array $formInstance
     * @param FormResult          $requestResult
     * @return array
     */
    public function getFieldsValuesDataAttributes($formInstance, FormResult $requestResult)
    {
        $result = [];

        foreach ($this->getFormConfiguration()->getFields() as $fieldName => $fieldConfiguration) {
            if (false === in_array($fieldName, $this->getFormDeactivatedFields($requestResult))
                && ((
                        is_object($formInstance)
                        && ObjectAccess::isPropertyGettable($formInstance, $fieldName)
                    )
                    || (
                        is_array($formInstance)
                        && true === isset($formInstance[$fieldName])
                    ))
            ) {
                $value = ObjectAccess::getProperty($formInstance, $fieldName);
                $value = (is_array($value))
                    ? implode(' ', $value)
                    : $value;

                $result[self::getFieldDataValueKey($fieldName)] = $value;
            }
        }

        return $result;
    }

    /**
     * Handles the data attributes for the fields which got errors.
     *
     * Example:
     *  - `formz-error-email="1"`
     *  - `formz-error-email-rule-default="1"`
     *
     * @param FormResult $requestResult
     * @return array
     */
    public function getFieldsErrorsDataAttributes(FormResult $requestResult)
    {
        $result = [];

        foreach ($requestResult->getSubResults() as $fieldName => $fieldResult) {
            if (false === in_array($fieldName, $this->getFormDeactivatedFields($requestResult))
                && true === $this->getFormConfiguration()->hasField($fieldName)
            ) {
                /** @var Result $fieldResult */
                if (true === $fieldResult->hasErrors()
                    && null === $requestResult->getData(AbstractFormValidator::RESULT_KEY_ACTIVATION_PROPERTY . '.' . $fieldName)
                ) {
                    $result[self::getFieldDataErrorKey($fieldName)] = '1';

                    foreach ($fieldResult->getErrors() as $error) {
                        /** @var Error $error */
                        $errorTitle = ($error->getTitle())
                            ? $error->getTitle()
                            : 'default';
                        $result[self::getFieldDataValidationErrorKey($fieldName, $errorTitle)] = '1';
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Handles the data attributes for the fields which are valid.
     *
     * Example:
     *  - `formz-valid-email="1"`
     *
     * @param FormResult $requestResult
     * @return array
     */
    public function getFieldsValidDataAttributes(FormResult $requestResult)
    {
        $result = [];

        foreach ($this->getFormConfiguration()->getFields() as $field) {
            $fieldName = $field->getFieldName();

            if (false === in_array($fieldName, $this->getFormDeactivatedFields($requestResult))
                && false === $requestResult->forProperty($fieldName)->hasErrors()
                && null === $requestResult->getData(AbstractFormValidator::RESULT_KEY_ACTIVATION_PROPERTY . '.' . $fieldName)
            ) {
                $result[self::getFieldDataValidKey($fieldName)] = '1';
            }
        }

        return $result;
    }

    /**
     * Formats the data value attribute key for a given field name.
     *
     * @param string $fieldName Name of the field.
     * @return string
     */
    public static function getFieldDataValueKey($fieldName)
    {
        return 'formz-value-' . self::getFieldCleanName($fieldName);
    }

    /**
     * Formats the data valid attribute key for a given field name.
     *
     * @param string $fieldName Name of the field.
     * @return string
     */
    public static function getFieldDataValidKey($fieldName)
    {
        return 'formz-valid-' . self::getFieldCleanName($fieldName);
    }

    /**
     * Formats the data error attribute key for a given field name.
     *
     * @param string $fieldName Name of the field.
     * @return string
     */
    public static function getFieldDataErrorKey($fieldName)
    {
        return 'formz-error-' . self::getFieldCleanName($fieldName);
    }

    /**
     * Formats the data error attribute key for a given failed validation for
     * the given field name.
     *
     * @param string $fieldName  Name of the field.
     * @param string $errorTitle Title of the error.
     * @return string
     */
    public static function getFieldDataValidationErrorKey($fieldName, $errorTitle)
    {
        return 'formz-error-' . self::getFieldCleanName($fieldName) . '-' . self::getFieldCleanName(str_replace(':', '-', $errorTitle));
    }

    /**
     * Replaces underscores with a dash.
     *
     * @param string $fieldName
     * @return string
     */
    public static function getFieldCleanName($fieldName)
    {
        return str_replace('_', '-', GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName));
    }

    /**
     * @param FormResult $requestResult
     * @return array
     */
    protected function getFormDeactivatedFields(FormResult $requestResult)
    {
        return array_keys($requestResult->getData(AbstractFormValidator::RESULT_KEY_ACTIVATION_PROPERTY));
    }
}
