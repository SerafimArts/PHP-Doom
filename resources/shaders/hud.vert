#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "quad.glsl"

// A picture on the screen of 320 x 200 points: the corner, the size, the picture, the row of the colormap and the mirror.
struct HudQuad
{
    vec2 position;
    vec2 size;
    uint picture;
    int row;
    uint flipped;
    uint padding;
};

layout(std430, set = 0, binding = 10) readonly buffer HudQuads
{
    HudQuad quads[];
};

layout(location = 0) out vec2 outTexel;
layout(location = 1) flat out uint outPicture;
layout(location = 2) flat out int outRow;
layout(location = 3) flat out uvec2 outSize;

void main()
{
    HudQuad quad = quads[gl_InstanceIndex];
    vec2 corner = CORNERS[gl_VertexIndex];

    vec2 point = quad.position + corner * quad.size;

    gl_Position = vec4(point / vec2(160.0, 100.0) - 1.0, 0.0, 1.0);

    float column = quad.flipped != 0u ? 1.0 - corner.x : corner.x;

    outTexel = vec2(column, corner.y) * quad.size;
    outPicture = quad.picture;
    outRow = quad.row;
    outSize = uvec2(quad.size);
}
