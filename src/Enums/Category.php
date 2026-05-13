<?php

namespace Geekset\OctaneDoctor\Enums;

enum Category: string
{
    case ContainerLifecycle = 'container-lifecycle';
    case RequestState = 'request-state';
    case StaticState = 'static-state';
    case SingletonSafety = 'singleton-safety';
    case RuntimeIsolation = 'runtime-isolation';
    case PackageCompatibility = 'package-compatibility';
    case Configuration = 'configuration';
    case UnknownRisk = 'unknown-risk';
}
