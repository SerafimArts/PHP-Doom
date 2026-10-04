#version 460
#ifdef RAY_SHADOWS
#extension GL_EXT_ray_query : require
#endif
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "shading.glsl"
#include "shadow.glsl"

layout(location = 0) in vec2 inPosition;
layout(location = 1) flat in uint inPicture;
layout(location = 2) flat in uint inLight;
layout(location = 3) flat in uint inIsSky;
layout(location = 4) in vec3 inWorld;
layout(location = 5) flat in vec3 inNormal;

layout(location = 0) out vec4 outColor;

void main()
{
    // A sky floor or ceiling shows the sky pass that was drawn first.
    if (inIsSky != 0u)
    {
        discard;
    }

    // Flats are aligned to the map: a texel is a map unit, rows go south.
    vec2 picture = fetchPicture(inPicture, ivec2(int(floor(inPosition.x)), int(floor(-inPosition.y))));

    float depth = 1.0 / gl_FragCoord.w;

    vec3 glow = lightSurface(inWorld, inNormal, MASK_SURFACE);
    vec3 color = shade(picture.x, flatRow(lightStart(inLight, lightBoost(glow)), depth));

    outColor = vec4(applyLight(color, glow), 1.0);
}
