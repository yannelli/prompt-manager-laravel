<?php

namespace Yannelli\PromptManager\Enums;

enum ComponentStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Conditional = 'conditional';
}
