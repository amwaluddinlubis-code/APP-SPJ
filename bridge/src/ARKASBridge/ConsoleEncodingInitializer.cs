using System;
using System.Runtime.CompilerServices;
using System.Text;

namespace ARKASBridge;

internal static class ConsoleEncodingInitializer
{
    [ModuleInitializer]
    internal static void Initialize()
    {
        Console.OutputEncoding = Encoding.UTF8;
        Console.InputEncoding = Encoding.UTF8;
    }
}
