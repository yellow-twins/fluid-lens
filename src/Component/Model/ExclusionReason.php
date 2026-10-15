<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * Why a structure must stay a Partial instead of becoming a component.
 */
enum ExclusionReason: string
{
    case FormContext = 'form-context';
    case CallerSection = 'caller-section';
    case Layout = 'layout';
    case TemplateSection = 'template-section';

    public function message(): string
    {
        return match ($this) {
            self::FormContext => 'uses f:form.* fields without their <f:form>; a component renders with its own'
                . ' ViewHelper context, so the fields would lose the surrounding form',
            self::CallerSection => 'renders a section of the calling template, which a component cannot see',
            self::Layout => 'belongs to a layout; layouts and their sections are not component material',
            self::TemplateSection => 'is a template section; sections are rendered by the template itself',
        };
    }
}
