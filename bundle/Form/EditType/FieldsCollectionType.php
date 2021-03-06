<?php
/**
 * NovaFormBuilder Bundle.
 *
 * @package   Novactive\Bundle\FormBuilderBundle
 *
 * @author    Novactive <s.morel@novactive.com>
 * @copyright 2018 Novactive
 * @license   https://github.com/Novactive/NovaFormBuilderBundle/blob/master/LICENSE MIT Licence
 */

declare(strict_types=1);

namespace Novactive\Bundle\FormBuilderBundle\Form\EditType;

use InvalidArgumentException;
use Novactive\Bundle\FormBuilderBundle\Core\Field\FieldTypeInterface;
use Novactive\Bundle\FormBuilderBundle\Entity\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldsCollectionType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'novaformbuilder_fields_collection';
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldTypesPrototype = [];
        $prototypeOptions    = array_replace(
            [
                'required' => $options['required'],
                'label'    => $options['prototype_name'].'label__',
            ],
            $options['entry_options']
        );
        /** @var FieldTypeInterface $fieldType */
        foreach ($options['field_types'] as $fieldType) {
            $prototypeOptions['data'] = $fieldType->newEntity();
            $prototype                = $builder->create(
                $options['prototype_name'],
                $options['entry_type'],
                $prototypeOptions
            );

            $fieldTypesPrototype[$fieldType->getIdentifier()] = $prototype->getForm();
        }

        $builder->setAttribute('field_types_prototype', $fieldTypesPrototype);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options) {
                /** @var Field $field */
                $field = (array) $event->getData();
                $form  = $event->getForm();

                /** @var FieldTypeInterface[] $fieldTypes */
                $fieldTypes = $form->getConfig()->getOption('field_types');

                foreach ($field as $name => $value) {
                    if ($form->has($name)) {
                        continue;
                    }
                    $fieldType = $fieldTypes[$value['type']] ?? null;
                    if (!$fieldType instanceof FieldTypeInterface) {
                        throw new InvalidArgumentException(
                            'A FieldType not implementing FieldTypeInterface has been passed: '.
                            \get_class($fieldType)
                        );
                    }

                    // Set options for new rows
                    $form->add(
                        $name,
                        FieldEditType::class,
                        array_replace(
                            [
                                'property_path'      => '['.$name.']',
                                'data_class'         => $fieldType->getEntityClass(),
                                'allow_extra_fields' => true,
                                'by_reference'       => false,
                                'data'               => $fieldType->newEntity(),
                            ],
                            $options['entry_options']
                        )
                    );
                }
            },
            1000
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($form->getConfig()->hasAttribute('field_types_prototype')) {
            /** @var FormInterface $prototypes */
            $prototypes          = $form->getConfig()->getAttribute('field_types_prototype');
            $fieldTypesPrototype = [];

            foreach ($prototypes as $fieldTypeIdentifier => $prototype) {
                $fieldTypesPrototype[$fieldTypeIdentifier] = $prototype->setParent($form)->createView($view);
            }

            $view->vars['field_types_prototype'] = $fieldTypesPrototype;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('field_types', []);
    }
}
