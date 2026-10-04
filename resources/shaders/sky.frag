#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "shading.glsl"

layout(location = 0) in float inX;

layout(location = 0) out vec4 outColor;

const float TAU = 6.28318530718;

// The sky is a cylinder around the viewer: 1024 columns per turn, the picture is 256 wide and repeats. The rows follow
// the screen at the 320x200 scale, the sky does not move when the viewer looks up or down.
void main()
{
    float direction = frame.view.x - atan(inX * frame.view.y);
    int column = int(floor(direction / TAU * 1024.0));
    int row = int(floor(gl_FragCoord.y / float(frame.parameters.z) * 200.0));

    vec2 sky = fetchPicture(frame.parameters.w, ivec2(column, row));

    outColor = vec4(shade(sky.x, 0), 1.0);
}
