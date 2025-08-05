<?php

namespace ApexToolbox\SymfonyLogger;

class SourceClassExtractor
{
    public function extractSourceClass(array $context = []): ?string
    {
        // First, check if source class is already provided in context
        if (isset($context['source_class']) && is_string($context['source_class'])) {
            return $context['source_class'];
        }

        // Try to extract from context (e.g., class name in message or context) first
        $sourceClass = $this->extractFromContext($context);
        if ($sourceClass) {
            return $sourceClass;
        }

        // Fall back to extracting from backtrace
        $sourceClass = $this->extractFromBacktrace();
        if ($sourceClass) {
            return $sourceClass;
        }

        return null;
    }

    private function extractFromBacktrace(): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        // Skip internal logging classes to find the actual source
        $skipClasses = [
            'Monolog\\',
            'ApexToolbox\\SymfonyLogger\\',
            'Symfony\\Component\\HttpKernel\\Log\\',
            'Psr\\Log\\',
            'PHPUnit\\Framework\\',
            'PHPUnit\\TextUI\\',
            'PHPUnit\\Util\\',
        ];
        
        foreach ($backtrace as $trace) {
            if (!isset($trace['class'])) {
                continue;
            }
            
            $class = $trace['class'];
            
            // Skip internal logging classes
            $shouldSkip = false;
            foreach ($skipClasses as $skipClass) {
                if (str_starts_with($class, $skipClass)) {
                    $shouldSkip = true;
                    break;
                }
            }
            
            if ($shouldSkip) {
                continue;
            }
            
            // Return the first non-logging class we find
            return $class;
        }
        
        return null;
    }

    private function extractFromContext(array $context): ?string
    {
        // Check for common context keys that might contain class information
        $classKeys = ['class', 'service', 'component', 'logger'];
        
        foreach ($classKeys as $key) {
            if (isset($context[$key]) && is_string($context[$key])) {
                // If it looks like a class name, return it
                if ($this->looksLikeClassName($context[$key])) {
                    return $context[$key];
                }
            }
        }
        
        // Check if any context values look like class names
        foreach ($context as $value) {
            if (is_string($value) && $this->looksLikeClassName($value)) {
                return $value;
            }
        }
        
        return null;
    }

    private function looksLikeClassName(string $value): bool
    {
        // Basic heuristics to identify class names
        return preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*[a-zA-Z0-9]$/', $value) === 1 &&
               (str_contains($value, '\\') || ctype_alpha($value[0]));
    }
}