#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"

layout(location = 0) out float outX;

// One triangle that covers the whole canvas. The sky depends on the screen position only.
void main()
{
    vec2 position = vec2(float((gl_VertexIndex << 1) & 2), float(gl_VertexIndex & 2)) * 2.0 - 1.0;

    gl_Position = vec4(position, 1.0, 1.0);
    outX = position.x;
}
